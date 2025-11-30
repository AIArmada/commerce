<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a purchase is successfully paid.
 */
final class PurchasePaid extends PurchaseEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PurchasePaid;
    }

    /**
     * Get the payment amount (net after fees) in cents.
     */
    public function getNetAmount(): int
    {
        return $this->purchase->payment?->getNetAmountInCents() ?? $this->getAmount();
    }

    /**
     * Get the fee amount charged in cents.
     */
    public function getFeeAmount(): int
    {
        return $this->purchase->payment?->getFeeAmountInCents() ?? 0;
    }
}
