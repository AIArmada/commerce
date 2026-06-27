<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Enums\WebhookEventType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a refund payment is completed.
 */
final class PaymentRefunded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly ?PaymentData $payment,
        public readonly array $payload,
    ) {}

    /**
     * Create event from a raw webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $payment = PaymentData::fromWebhookPayload($payload);

        return new self(
            payment: $payment,
            payload: $payload,
        );
    }

    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PaymentRefunded;
    }

    /**
     * Get the refund amount in cents.
     */
    public function getAmount(): int
    {
        return $this->payment?->getAmountInCents() ?? 0;
    }

    /**
     * Get the currency code.
     */
    public function getCurrency(): string
    {
        return $this->payment?->getCurrency() ?? 'MYR';
    }

    /**
     * Get the purchase ID.
     */
    public function getPurchaseId(): ?string
    {
        return $this->payment?->getRelatedPurchaseId();
    }

    /**
     * Get the reference.
     */
    public function getReference(): ?string
    {
        return $this->payment?->getReference();
    }

    /**
     * Check if this is a test payment.
     */
    public function isTest(): bool
    {
        return $this->payment?->isTest() ?? false;
    }
}
