<?php

namespace App\Services;

use App\Enums\Supplier;

final class SupplierIssueResult
{
    public function __construct(
        public string $code,
        public Supplier $supplier,
    ) {
    }
}
