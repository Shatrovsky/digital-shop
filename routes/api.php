<?php

use App\Http\Controllers\SupplierStubController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/payment', [WebhookController::class, 'payment']);

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{orderId}', [OrderController::class, 'show']);

Route::post('/suppliers/a/issue', [SupplierStubController::class, 'issueA']);
Route::post('/suppliers/b/issue', [SupplierStubController::class, 'issueB']);
