<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when funds are placed on hold (skip_capture = true).
 */
final class PurchaseHold extends PurchaseEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PurchaseHold;
    }

    /**
     * Get the amount on hold.
     */
    public function getHoldAmount(): int
    {
        return $this->getAmount();
    }
}
