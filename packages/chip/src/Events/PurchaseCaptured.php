<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a held payment is captured.
 */
final class PurchaseCaptured extends PurchaseEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PurchaseCaptured;
    }
}
