<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a recurring token deletion is pending.
 */
final class PurchasePendingRecurringTokenDelete extends PurchaseEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PurchasePendingRecurringTokenDelete;
    }
}
