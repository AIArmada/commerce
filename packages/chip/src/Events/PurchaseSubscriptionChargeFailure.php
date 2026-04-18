<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a subscription charge fails.
 *
 * This happens when an attempt to charge a subscriber's saved card fails.
 */
final class PurchaseSubscriptionChargeFailure extends PurchaseEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PurchaseSubscriptionChargeFailure;
    }

    /**
     * Get the error message if available.
     */
    public function getErrorMessage(): ?string
    {
        $lastAttempt = $this->purchase->transaction_data->getLastAttempt();

        if ($lastAttempt === null) {
            return null;
        }

        return $lastAttempt['error']['message'] ?? null;
    }

    /**
     * Get the error code if available.
     */
    public function getErrorCode(): ?string
    {
        $lastAttempt = $this->purchase->transaction_data->getLastAttempt();

        if ($lastAttempt === null) {
            return null;
        }

        return $lastAttempt['error']['code'] ?? null;
    }

    /**
     * Get the billing template ID if available.
     */
    public function getBillingTemplateId(): ?string
    {
        return $this->purchase->billing_template_id;
    }
}
