<?php

namespace App\Exceptions;

use RuntimeException;

class SupplierUnavailableException extends RuntimeException
{
    public function __construct(string $message = '', public readonly bool $hard = false)
    {
        parent::__construct($message);
    }
}
