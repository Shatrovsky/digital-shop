<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Supplier;
use App\Exceptions\OutOfStockException;
use App\Jobs\DeliverOrder;
use App\Models\KeyPool;
use App\Models\ProductStock;
use App\Services\SupplierGatewayInterface;
use App\Services\SupplierIssueResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Feature\Support\OrderDeliveryTestHelper;

class StockTest extends TestCase
{
    use RefreshDatabase;
    use OrderDeliveryTestHelper;

    public function test_out_of_stock_is_recoverable(): void
    {
        $product = $this->seedProduct();
        $order = $this->createOrder($product);
        $order->update(['status' => OrderStatus::Paid]);

        ProductStock::where('sku', $product->sku)->update(['quantity' => 0]);

        $this->app->bind(SupplierGatewayInterface::class, function () {
            return new class implements SupplierGatewayInterface {
                public function issue(
                    Supplier $supplier,
                    string $requestId,
                    string $sku,
                    int $orderId
                ): SupplierIssueResult {
                    $stock = ProductStock::where('sku', $sku)->first();

                    if (! $stock || $stock->quantity <= 0) {
                        throw new OutOfStockException();
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

        $this->assertSame(OrderStatus::OutOfStock, $order->fresh()->status);

        ProductStock::where('sku', $product->sku)->update(['quantity' => 5]);

        $order->update(['delivery_lease_expires_at' => now()->subSecond()]);

        $job->handle(app(SupplierGatewayInterface::class));

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertDatabaseCount('deliveries', 1);
    }
}
