<?php

namespace App\Services;

use App\Enums\WebhookStatus;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\PaymentWebhook;
use Illuminate\Support\Carbon;

class WebhookReceiver
{
    public function receive(array $payload): bool
    {
        $eventId = (string) $payload['event_id'];
        $model = new PaymentWebhook([
            'event_id' => $eventId,
            'order_id' => $payload['order_id'] ?? null,
            'status' => WebhookStatus::Received->value,
            'payload' => $payload,
            'occurred_at' => isset($payload['created_at'])
                ? Carbon::parse($payload['created_at'])
                : now(),
        ]);

        $inserted = $model->saveOrIgnore();

        if ($inserted) {
            ProcessPaymentWebhook::dispatch($eventId)->onQueue('webhooks');
        }

        return $inserted;
    }
}
