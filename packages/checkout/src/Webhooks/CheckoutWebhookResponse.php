<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookResponse\RespondsToWebhook;
use Symfony\Component\HttpFoundation\Response;

final class CheckoutWebhookResponse implements RespondsToWebhook
{
    public function respondToValidWebhook(Request $request, WebhookConfig $config): Response
    {
        return response()->json([
            'status' => 'accepted',
        ]);
    }
}
