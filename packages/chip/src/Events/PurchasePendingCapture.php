<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a capture is pending on the acquirer side.
 */
final class PurchasePendingCapture extends PurchaseEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PurchasePendingCapture;
    }
}
