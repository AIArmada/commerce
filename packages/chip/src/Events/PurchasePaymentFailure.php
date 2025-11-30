<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a purchase payment fails.
 */
final class PurchasePaymentFailure extends PurchaseEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PurchasePaymentFailure;
    }

    /**
     * Get the error message if available.
     */
    public function getErrorMessage(): ?string
    {
        $attempts = $this->purchase->transaction_data->attempts ?? [];
        $lastAttempt = end($attempts);

        return $lastAttempt['error']['message'] ?? null;
    }

    /**
     * Get the error code if available.
     */
    public function getErrorCode(): ?string
    {
        $attempts = $this->purchase->transaction_data->attempts ?? [];
        $lastAttempt = end($attempts);

        return $lastAttempt['error']['code'] ?? null;
    }
}
