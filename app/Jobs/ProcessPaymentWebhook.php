<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\WebhookStatus;
use App\Models\Order;
use App\Models\PaymentWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function backoff(): array
    {
        return [1, 2, 5, 10, 20, 30, 60];
    }

    public function __construct(
        public string $eventId
    ) {
    }

    public function handle(): void
    {
        $webhook = PaymentWebhook::query()->find($this->eventId);

        if (! $webhook) {
            return;
        }

        $payload = $webhook->payload;
        $orderId = (int) ($payload['order_id'] ?? 0);

        if (! $orderId) {
            $webhook->update(['status' => WebhookStatus::Ignored->value]);
            return;
        }

        $orderExists = Order::query()->whereKey($orderId)->exists();

        if (! $orderExists) {
            if ($this->attempts() < 20) {
                $this->release(2);
                return;
            }

            $webhook->update(['status' => WebhookStatus::Ignored->value]);
            return;
        }

        DB::transaction(function () use ($webhook, $payload, $orderId) {
            /** @var Order $order */
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $payload['status'] ?? null;

            if ($status === 'failed') {
                if (! $order->status->isFinal()) {
                    $order->update([
                        'status' => OrderStatus::PaymentFailed,
                    ]);
                }

                $this->markProcessed($webhook);
                return;
            }

            if ($status !== 'paid') {
                $this->markIgnored($webhook);
                return;
            }

            $amountMatches = (int) ($payload['amount'] ?? 0) === (int) $order->amount;
            $currencyMatches = ($payload['currency'] ?? null) === $order->currency;

            if (! $amountMatches || ! $currencyMatches) {
                $this->markIgnored($webhook);
                return;
            }

            if ($order->status === OrderStatus::Created) {
                $order->update([
                    'status' => OrderStatus::Paid,
                    'paid_at' => now(),
                    'payment_event_id' => $webhook->event_id,
                ]);
            }

            $order->refresh();

            if ($order->status->isFinal()) {
                $this->markProcessed($webhook);
                return;
            }

            $started = Order::query()
                ->whereKey($order->id)
                ->whereIn('status', [
                    OrderStatus::Paid,
                    OrderStatus::OutOfStock,
                    OrderStatus::DeliveryFailed,
                ])
                ->whereNull('delivered_at')
                ->update([
                    'status' => OrderStatus::Delivering,
                    'updated_at' => now(),
                ]);

            if ($started > 0) {
                DB::afterCommit(function () use ($order) {
                    DeliverOrder::dispatch($order->id)->onQueue('delivery');
                });
            }

            $this->markProcessed($webhook);
        });
    }

    protected function markProcessed(PaymentWebhook $webhook): void
    {
        $webhook->update([
            'status' => WebhookStatus::Processed->value,
            'processed_at' => now(),
        ]);
    }

    protected function markIgnored(PaymentWebhook $webhook): void
    {
        $webhook->update([
            'status' => WebhookStatus::Ignored->value,
            'processed_at' => now(),
        ]);
    }
}
