<?php

namespace App\Services;

use App\Enums\Supplier;

interface SupplierGatewayInterface
{
    public function issue(
        Supplier $supplier,
        string $requestId,
        string $sku,
        int $orderId
    ): SupplierIssueResult;
}
