<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a card is preauthorized (card saved without financial transaction).
 */
final class PurchasePreauthorized extends PurchaseEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PurchasePreauthorized;
    }

    /**
     * Check if this preauthorization saved a recurring token.
     */
    public function savedRecurringToken(): bool
    {
        return $this->purchase->is_recurring_token;
    }
}
