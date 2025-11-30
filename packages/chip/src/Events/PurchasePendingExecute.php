<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a purchase execution is pending on the acquirer side.
 */
final class PurchasePendingExecute extends PurchaseEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PurchasePendingExecute;
    }
}
