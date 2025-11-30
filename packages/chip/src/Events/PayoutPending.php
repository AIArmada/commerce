<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a payout is pending/processing.
 */
final class PayoutPending extends PayoutEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PayoutPending;
    }
}
