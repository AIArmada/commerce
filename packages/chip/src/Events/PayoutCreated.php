<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a CHIP payout is created.
 */
final class PayoutCreated extends PayoutEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PayoutCreated;
    }
}
