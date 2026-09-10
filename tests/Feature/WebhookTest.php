<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\ProcessPendingWebhooks;
use App\Models\KeyPool;
use App\Models\Order;
use App\Services\SupplierGatewayInterface;
use App\Services\SupplierIssueResult;
use App\Enums\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Feature\Support\OrderDeliveryTestHelper;

class WebhookTest extends TestCase
{
    use RefreshDatabase;
    use OrderDeliveryTestHelper;

    protected function bindSuccessGateway(): void
    {
        $this->app->bind(SupplierGatewayInterface::class, function () {
            return new class implements SupplierGatewayInterface {
                public function issue(
                    Supplier $supplier,
                    string $requestId,
                    string $sku,
                    int $orderId
                ): SupplierIssueResult {
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
    }

    protected function webhookPayload(Order $order, string $eventId): array
    {
        return [
            'event_id' => $eventId,
            'order_id' => $order->id,
            'status' => 'paid',
            'amount' => $order->amount,
            'currency' => $order->currency,
            'created_at' => now()->toISOString(),
        ];
    }

    public function test_repeated_webhook_with_same_event_id_changes_nothing(): void
    {
        $product = $this->seedProduct();
        $order = $this->createOrder($product);

        $this->bindSuccessGateway();

        $payload = $this->webhookPayload($order, 'evt_same');

        $this->postJson('/api/webhook/payment', $payload)->assertOk();
        $this->postJson('/api/webhook/payment', $payload)->assertOk();

        $this->assertDatabaseCount('deliveries', 1);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_many_paid_webhooks_for_one_order(): void
    {
        $product = $this->seedProduct();
        $order = $this->createOrder($product);

        $this->bindSuccessGateway();

        for($i = 1; $i <= 50; $i++) {
            $this->postJson('/api/webhook/payment', $this->webhookPayload($order, "evt_{$i}"))->assertOk();
        }

        $this->assertDatabaseCount('deliveries', 1);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_webhook_before_order_is_processed_after_order_created(): void
    {
        $product = $this->seedProduct();
        $orderId = 999001;

        $this->bindSuccessGateway();

        $payload = [
            'event_id' => 'evt_before_order',
            'order_id' => $orderId,
            'status' => 'paid',
            'amount' => $product->price,
            'currency' => $product->currency,
            'created_at' => now()->toISOString(),
        ];

        $this->postJson('/api/webhook/payment', $payload)->assertOk();

        $order = $this->createOrder($product, $orderId);

        ProcessPendingWebhooks::dispatchSync($orderId);

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertDatabaseCount('deliveries', 1);
    }
}
