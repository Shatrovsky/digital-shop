<?php

namespace App\Services;

use App\Enums\Supplier;
use App\Exceptions\OutOfStockException;
use App\Exceptions\SupplierErrorException;
use App\Exceptions\SupplierTimeoutException;
use App\Exceptions\SupplierUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpSupplierGateway implements SupplierGatewayInterface
{
    public function issue(
        Supplier $supplier,
        string $requestId,
        string $sku,
        int $orderId
    ): SupplierIssueResult {
        $config = config('services.suppliers.' . $supplier->value);

        try {
            $response = Http::timeout($config['timeout'] ?? 2)
                ->connectTimeout(1)
                ->post($config['url'], [
                    'request_id' => $requestId,
                    'sku' => $sku,
                    'order_id' => $orderId,
                ]);
        } catch (ConnectionException $e) {
            $hard = str_contains($e->getMessage(), 'Could not resolve host')
                || str_contains($e->getMessage(), 'Connection refused');

            throw new SupplierUnavailableException($e->getMessage(), $hard);
        } catch (Throwable $e) {
            throw new SupplierUnavailableException($e->getMessage(), false);
        }

        if ($response->status() === 0) {
            throw new SupplierTimeoutException();
        }

        if ($response->status() === 409 || $response->json('reason') === 'out_of_stock') {
            throw new OutOfStockException();
        }

        if ($response->status() >= 500) {
            throw new SupplierUnavailableException('Supplier returned 5xx', false);
        }

        if ($response->failed()) {
            throw new SupplierErrorException('Supplier returned error status');
        }

        return new SupplierIssueResult(
            code: (string) $response->json('code'),
            supplier: $supplier,
        );
    }
}
