<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a recurring token is deleted.
 */
final class PurchaseRecurringTokenDeleted extends PurchaseEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PurchaseRecurringTokenDeleted;
    }
}
