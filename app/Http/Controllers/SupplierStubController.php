<?php

namespace App\Http\Controllers;

use App\Enums\Supplier;
use App\Services\StubIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierStubController extends Controller
{
    public function issueA(Request $request, StubIssuer $issuer): JsonResponse
    {
        return $issuer->handle($request, Supplier::A);
    }

    public function issueB(Request $request, StubIssuer $issuer): JsonResponse
    {
        return $issuer->handle($request, Supplier::B);
    }
}
