<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when a payout fails.
 */
final class PayoutFailed extends PayoutEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PayoutFailed;
    }

    /**
     * Get the error message if available.
     */
    public function getErrorMessage(): ?string
    {
        return $this->payout->getErrorMessage();
    }

    /**
     * Get the error code if available.
     */
    public function getErrorCode(): ?string
    {
        return $this->payout->getErrorCode();
    }
}
