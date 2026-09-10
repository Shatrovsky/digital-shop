<?php

namespace App\Jobs;

use App\Enums\WebhookStatus;
use App\Models\PaymentWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPendingWebhooks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $orderId
    ) {
    }

    public function handle(): void
    {
        PaymentWebhook::query()
            ->where('order_id', $this->orderId)
            ->whereIn('status', [WebhookStatus::Received->value])
            ->each(function (PaymentWebhook $webhook) {
                ProcessPaymentWebhook::dispatch($webhook->event_id)->onQueue('webhooks');
            });
    }
}
