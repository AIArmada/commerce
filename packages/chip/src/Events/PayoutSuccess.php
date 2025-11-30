<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a payout is successfully completed.
 */
final class PayoutSuccess extends PayoutEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PayoutSuccess;
    }
}
