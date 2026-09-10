<?php

namespace Tests\Feature\support;

use App\Enums\OrderStatus;
use App\Models\KeyPool;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Support\Facades\DB;

trait OrderDeliveryTestHelper
{
    protected function seedProduct(string $sku = 'STEAM-TOPUP-500'): Product
    {
        Product::query()->updateOrCreate(
            ['sku' => $sku],
            [
                'name' => 'Test Product',
                'type' => 'topup',
                'price' => 500,
                'currency' => 'RUB',
                'is_active' => true,
            ]
        );

        ProductStock::query()->updateOrInsert(
            ['sku' => $sku],
            ['quantity' => 10, 'updated_at' => now()]
        );

        KeyPool::query()->insertOrIgnore([
            'code' => 'TEST-KEY-0001',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->where('sku', $sku)->first();
    }

    protected function createOrder(Product $product, ?int $id = null): Order
    {
        $base = [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::Created->value,
            'supplier_attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($id === null) {
            return Order::query()->create($base);
        }

        DB::table('orders')->insert($base + ['id' => $id]);

        return Order::query()->find($id);
    }
}
