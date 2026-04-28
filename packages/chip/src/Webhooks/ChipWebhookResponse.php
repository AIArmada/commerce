<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookResponse\RespondsToWebhook;
use Symfony\Component\HttpFoundation\Response;

final class ChipWebhookResponse implements RespondsToWebhook
{
    public function respondToValidWebhook(Request $request, WebhookConfig $config): Response
    {
        return response()->json([
            'status' => 'accepted',
        ]);
    }
}
