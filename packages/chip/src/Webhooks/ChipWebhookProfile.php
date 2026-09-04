<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProfile;
use Illuminate\Http\Request;

/**
 * Profile for determining if CHIP webhooks should be processed.
 */
class ChipWebhookProfile extends CommerceWebhookProfile
{
    /**
     * Determine if the request should be processed.
     */
    public function shouldProcess(Request $request): bool
    {
        $eventType = $request->input('event_type');

        return is_string($eventType) && WebhookEventType::tryFrom($eventType) !== null;
    }
}
