<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Jobs\ProcessPendingWebhooks;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'exists:products,sku'],
        ]);

        $product = Product::query()->where('sku', $data['sku'])->firstOrFail();

        $order = DB::transaction(function () use ($product) {
            return Order::query()->create([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'amount' => $product->price,
                'currency' => $product->currency,
                'status' => OrderStatus::Created,
            ]);
        });

        ProcessPendingWebhooks::dispatch($order->id)->onQueue('webhooks');

        return response()->json($order, 201);
    }

    public function show(int $orderId): JsonResponse
    {
        $order = Order::query()
            ->with('delivery')
            ->findOrFail($orderId);

        return response()->json($order);
    }
}
