<?php

namespace App\Services;

use App\Enums\Supplier;
use App\Exceptions\OutOfStockException;
use App\Models\KeyPool;
use App\Models\ProductStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StubIssuer
{
    public function handle(Request $request, Supplier $supplier): JsonResponse
    {
        $payload = $request->validate([
            'request_id' => ['required', 'string'],
            'sku' => ['required', 'string'],
            'order_id' => ['required', 'integer'],
        ]);

        $config = config('services.suppliers.' . $supplier->value);
        $mode = $request->header('X-Stub-Mode', $config['mode'] ?? 'ok');

        $requestId = $payload['request_id'];
        $sku = $payload['sku'];
        $orderId = (int) $payload['order_id'];

        if ($mode === 'unavailable') {
            return response()->json([
                'status' => 'error',
                'reason' => 'unavailable',
            ], 503);
        }

        if ($mode === 'out_of_stock') {
            return response()->json([
                'status' => 'error',
                'reason' => 'out_of_stock',
            ], 409);
        }

        if ($mode === 'error') {
            return response()->json([
                'status' => 'error',
                'reason' => 'internal_error',
            ], 500);
        }

        if ($mode === 'timeout_no_issue') {
            usleep((int) ($config['sleep_ms'] ?? 3000) * 1000);

            return response()->json([
                'status' => 'error',
                'reason' => 'timeout',
            ], 504);
        }

        if ($mode === 'timeout_after_issue') {
            ignore_user_abort(true);

            try {
                $code = $this->allocate($requestId, $sku, $orderId, $supplier);
            } catch (OutOfStockException) {
                return response()->json([
                    'status' => 'error',
                    'reason' => 'out_of_stock',
                ], 409);
            }

            usleep((int) ($config['sleep_ms'] ?? 3000) * 1000);

            return response()->json([
                'status' => 'ok',
                'request_id' => $requestId,
                'code' => $code,
            ]);
        }

        try {
            $code = $this->allocate($requestId, $sku, $orderId, $supplier);
        } catch (OutOfStockException) {
            return response()->json([
                'status' => 'error',
                'reason' => 'out_of_stock',
            ], 409);
        }

        return response()->json([
            'status' => 'ok',
            'request_id' => $requestId,
            'code' => $code,
        ]);
    }

    protected function allocate(
        string $requestId,
        string $sku,
        int $orderId,
        Supplier $supplier
    ): string {
        return DB::transaction(function () use ($requestId, $sku, $orderId, $supplier) {
            $stock = ProductStock::where('sku', $sku)->lockForUpdate()->first();

            if (! $stock || $stock->quantity <= 0) {
                throw new OutOfStockException();
            }

            $key = KeyPool::where('status', 'available')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $key) {
                throw new OutOfStockException();
            }

            ProductStock::where('sku', $sku)->decrement('quantity');

            KeyPool::where('id', $key->id)
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
                'code' => $key->code,
            ]);

            return $key->code;
        });
    }
}
