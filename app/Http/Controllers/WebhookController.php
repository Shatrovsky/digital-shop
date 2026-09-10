<?php

namespace App\Http\Controllers;

use App\Services\WebhookReceiver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function payment(Request $request, WebhookReceiver $receiver): JsonResponse
    {
        $payload = $request->validate([
            'event_id' => ['required', 'string'],
            'order_id' => ['required', 'integer'],
            'status' => ['required', 'string', 'in:paid,failed'],
            'amount' => ['required', 'integer'],
            'currency' => ['required', 'string', 'size:3'],
            'created_at' => ['required', 'date'],
        ]);

        $receiver->receive($payload);

        return response()->json([
            'status' => 'accepted',
        ]);
    }
}
