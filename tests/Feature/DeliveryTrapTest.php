<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Supplier;
use App\Exceptions\SupplierTimeoutException;
use App\Exceptions\SupplierUnavailableException;
use App\Jobs\DeliverOrder;
use App\Models\Delivery;
use App\Models\KeyPool;
use App\Models\Order;
use App\Services\SupplierGatewayInterface;
use App\Services\SupplierIssueResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Feature\Support\OrderDeliveryTestHelper;

class DeliveryTrapTest extends TestCase
{
    use RefreshDatabase;
    use OrderDeliveryTestHelper;

    protected function releaseLease(Order $order): void
    {
        $order->update([
            'delivery_lease_expires_at' => now()->subSecond(),
        ]);
    }

    public function test_timeout_after_real_issue_does_not_create_second_delivery(): void
    {
        $product = $this->seedProduct();
        $order = $this->createOrder($product);
        $order->update(['status' => OrderStatus::Paid]);

        $calls = 0;

        $this->app->bind(SupplierGatewayInterface::class, function () use (&$calls) {
            return new class($calls) implements SupplierGatewayInterface {
                public function __construct(public int &$calls)
                {
                }

                public function issue(
                    Supplier $supplier,
                    string $requestId,
                    string $sku,
                    int $orderId
                ): SupplierIssueResult {
                    $this->calls++;

                    if ($this->calls === 1) {
                        $code = 'TEST-KEY-0001';

                        KeyPool::where('code', $code)
                            ->update([
                                'status' => 'issued',
                                'order_id' => $orderId,
                                'issued_at' => now(),
                            ]);

                        DB::table('supplier_stub_ledger')->insert([
                            'request_id' => $requestId,
                            'order_id' => $orderId,
                            'sku' => $sku,
                            'supplier' => $supplier->value,
                            'code' => $code,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        throw new SupplierTimeoutException();
                    }

                    return new SupplierIssueResult(
                        DB::table('supplier_stub_ledger')->where('request_id', $requestId)->value('code'),
                        $supplier
                    );
                }
            };
        });

        $job = new DeliverOrder($order->id);

        $job->handle(app(SupplierGatewayInterface::class));

        $order->refresh();
        $this->releaseLease($order);

        $job->handle(app(SupplierGatewayInterface::class));

        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('supplier_stub_ledger', 1);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_supplier_a_unavailable_falls_back_to_b_and_issues_once(): void
    {
        $product = $this->seedProduct();
        $order = $this->createOrder($product);
        $order->update(['status' => OrderStatus::Paid]);

        $this->app->bind(SupplierGatewayInterface::class, function () {
            return new class implements SupplierGatewayInterface {
                public function issue(
                    Supplier $supplier,
                    string $requestId,
                    string $sku,
                    int $orderId
                ): SupplierIssueResult {
                    if ($supplier === Supplier::A) {
                        throw new SupplierUnavailableException('supplier A down', true);
                    }

                    $existing = DB::table('supplier_stub_ledger')
                        ->where('request_id', $requestId)
                        ->first();

                    if ($existing) {
                        return new SupplierIssueResult($existing->code, $supplier);
                    }

                    $code = 'TEST-KEY-0001';

                    KeyPool::where('code', $code)
                        ->update([
                            'status' => 'issued',
                            'order_id' => $orderId,
                            'issued_at' => now(),
                        ]);

                    DB::table('supplier_stub_ledger')->insert([
                        'request_id' => $requestId,
                        'order_id' => $orderId,
                        'sku' => $sku,
                        'supplier' => $supplier->value,
                        'code' => $code,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return new SupplierIssueResult($code, $supplier);
                }
            };
        });

        $job = new DeliverOrder($order->id);

        $job->handle(app(SupplierGatewayInterface::class));

        $order->refresh();
        $this->releaseLease($order);

        $job->handle(app(SupplierGatewayInterface::class));

        $this->assertDatabaseCount('deliveries', 1);
        $this->assertSame('b', Delivery::query()->first()->supplier);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }
}
