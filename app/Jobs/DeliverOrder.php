<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\Supplier;
use App\Exceptions\OutOfStockException;
use App\Exceptions\SupplierTimeoutException;
use App\Exceptions\SupplierUnavailableException;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\SupplierGatewayInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeliverOrder implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function backoff(): array
    {
        return [1, 2, 4, 8, 15, 30, 60];
    }

    public function __construct(
        public int $orderId
    ) {
    }

    public function handle(SupplierGatewayInterface $gateway): void
    {
        $order = Order::find($this->orderId);

        if (! $order || $order->status->isFinal()) {
            return;
        }

        if ($this->isAlreadyDelivered($order)) {
            return;
        }

        if (! $this->acquireDeliveryLock($order)) {
            $this->release(5);
            return;
        }

        $order->refresh();
        $this->processDelivery($order, $gateway);
    }

    /**
     * Проверяет, есть ли уже запись о доставке, и обновляет статус заказа.
     */
    private function isAlreadyDelivered(Order $order): bool
    {
        if (!Delivery::where('order_id', $order->id)->exists()) {
            return false;
        }

        $order->update([
            'status' => OrderStatus::Delivered,
            'delivered_at' => now(),
        ]);

        return true;
    }

    /**
     * Атомарно захватывает заказ (lease lock), чтобы избежать гонки данных (race conditions).
     */
    private function acquireDeliveryLock(Order $order): bool
    {
        $acquired = Order::query()
            ->whereKey($order->id)
            ->whereIn('status', [
                OrderStatus::Paid,
                OrderStatus::Delivering,
                OrderStatus::OutOfStock,
                OrderStatus::DeliveryFailed,
            ])
            ->where(function ($query) {
                $query->whereNull('delivery_lease_expires_at')
                    ->orWhere('delivery_lease_expires_at', '<', now());
            })
            ->update([
                'status' => OrderStatus::Delivering,
                'delivery_lease_expires_at' => now()->addSeconds(90),

                'delivery_request_id' => $order->delivery_request_id ?? "req_order_{$order->id}",
                'current_supplier' => $order->current_supplier ?? Supplier::A->value,
                'supplier_attempts' => $order->supplier_attempts ?? 0,
                'updated_at' => now(),
            ]);

        return $acquired > 0;
    }

    /**
     * Непосредственно вызов шлюза поставщика и обработка результатов.
     */
    private function processDelivery(Order $order, SupplierGatewayInterface $gateway): void
    {
        $supplier = Supplier::from($order->current_supplier ?? Supplier::A->value);
        $requestId = $order->delivery_request_id ?: "req_order_{$order->id}";

        try {
            $result = $gateway->issue(
                supplier: $supplier,
                requestId: $requestId,
                sku: $order->sku,
                orderId: $order->id,
            );

            $this->finalize($order, $result->code, $supplier->value, $requestId);
        } catch (OutOfStockException) {
            $this->markOrderAs($order, OrderStatus::OutOfStock);
        } catch (SupplierTimeoutException | SupplierUnavailableException $e) {
            $this->registerSupplierFailure($order, $supplier, $e);
        } catch (Throwable $e) {
            report($e);
            $this->markOrderAs($order, OrderStatus::DeliveryFailed);
        }
    }

    private function markOrderAs(Order $order, OrderStatus $status): void
    {
        $order->update([
            'status' => $status,
            'delivery_lease_expires_at' => now(),
        ]);
    }
    protected function finalize(Order $order, string $code, string $supplier, string $requestId): void
    {
        DB::transaction(function () use ($order, $code, $supplier, $requestId) {
            Delivery::query()->firstOrCreate(
                ['order_id' => $order->id],
                [
                    'code' => $code,
                    'supplier' => $supplier,
                    'request_id' => $requestId,
                    'issued_at' => now(),
                ]
            );

            Order::query()
                ->whereKey($order->id)
                ->update([
                    'status' => OrderStatus::Delivered,
                    'delivered_at' => now(),
                    'delivery_lease_expires_at' => now(),
                    'updated_at' => now(),
                ]);
        });
    }

    protected function registerSupplierFailure(
        Order $order,
        Supplier $supplier,
        Throwable $exception
    ): void {
        $order->refresh();

        $attempts = (int) $order->supplier_attempts + 1;

        $maxA = (int) config('services.suppliers.a.max_attempts', 3);
        $maxB = (int) config('services.suppliers.b.max_attempts', 3);

        $hardUnavailable = $exception instanceof SupplierUnavailableException && $exception->hard;

        // Если A жёстко недоступен, сразу уходим на B.
        if ($supplier === Supplier::A && ($hardUnavailable || $attempts >= $maxA)) {
            Order::query()
                ->whereKey($order->id)
                ->update([
                    'current_supplier' => Supplier::B->value,
                    'supplier_attempts' => 0,
                    'status' => OrderStatus::Delivering,
                    'delivery_lease_expires_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->release(1);
            return;
        }

        $max = $supplier === Supplier::A ? $maxA : $maxB;

        if ($attempts >= $max) {
            if ($supplier === Supplier::A) {
                Order::query()
                    ->whereKey($order->id)
                    ->update([
                        'current_supplier' => Supplier::B->value,
                        'supplier_attempts' => 0,
                        'status' => OrderStatus::Delivering,
                        'delivery_lease_expires_at' => now(),
                        'updated_at' => now(),
                    ]);

                $this->release(1);
                return;
            }

            Order::query()
                ->whereKey($order->id)
                ->update([
                    'status' => OrderStatus::DeliveryFailed,
                    'delivery_lease_expires_at' => now(),
                    'updated_at' => now(),
                ]);

            return;
        }

        $delay = (int) pow(2, min($attempts, 6));

        Order::query()
            ->whereKey($order->id)
            ->update([
                'supplier_attempts' => $attempts,
                'status' => OrderStatus::Delivering,
                'delivery_lease_expires_at' => now()->addSeconds($delay + 10),
                'updated_at' => now(),
            ]);

        $this->release($delay);
    }

    public function failed(Throwable $exception): void
    {
        Order::query()
            ->whereKey($this->orderId)
            ->whereIn('status', [
                OrderStatus::Paid,
                OrderStatus::Delivering,
                OrderStatus::OutOfStock,
                OrderStatus::DeliveryFailed,
            ])
            ->update([
                'status' => OrderStatus::DeliveryFailed,
                'delivery_lease_expires_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
