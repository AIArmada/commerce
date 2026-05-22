<?php

declare(strict_types=1);

namespace AIArmada\Chip\Gateways;

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use Akaunting\Money\Money;
use DateTimeInterface;

/**
 * Adapter that wraps a CHIP Purchase to implement PaymentIntentInterface.
 *
 * This allows CHIP purchases to be used interchangeably with payment intents
 * from other gateways (Stripe, PayPal, etc.).
 */
final readonly class ChipPaymentIntent implements PaymentIntentInterface
{
    public function __construct(
        private PurchaseData $purchase
    ) {}

    public function getPaymentId(): string
    {
        return $this->purchase->id;
    }

    public function getReference(): ?string
    {
        return $this->purchase->reference;
    }

    public function getAmount(): Money
    {
        return $this->purchase->getAmount();
    }

    public function getStatus(): PaymentStatus
    {
        return $this->mapChipStatus($this->purchase->status);
    }

    public function getCheckoutUrl(): ?string
    {
        return $this->purchase->checkout_url;
    }

    public function getSuccessUrl(): ?string
    {
        return $this->purchase->success_redirect;
    }

    public function getFailureUrl(): ?string
    {
        return $this->purchase->failure_redirect;
    }

    public function isPaid(): bool
    {
        return in_array($this->getStatus(), [
            PaymentStatus::PAID,
            PaymentStatus::PARTIALLY_REFUNDED,
            PaymentStatus::REFUNDED,
        ], true) || $this->purchase->marked_as_paid;
    }

    public function isPending(): bool
    {
        return $this->getStatus()->isPending();
    }

    public function isFailed(): bool
    {
        return $this->getStatus() === PaymentStatus::FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->getStatus() === PaymentStatus::CANCELLED;
    }

    public function isRefunded(): bool
    {
        return in_array($this->getStatus(), [
            PaymentStatus::PARTIALLY_REFUNDED,
            PaymentStatus::REFUNDED,
        ], true);
    }

    public function getRefundableAmount(): Money
    {
        return $this->purchase->getRefundableAmount();
    }

    public function isTest(): bool
    {
        return $this->purchase->is_test;
    }

    public function getGatewayName(): string
    {
        return 'chip';
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->purchase->getCreatedAt();
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->purchase->getUpdatedAt();
    }

    public function getPaidAt(): ?DateTimeInterface
    {
        return $this->purchase->payment?->getPaidAt();
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->purchase->purchase->metadata ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawResponse(): array
    {
        return $this->purchase->toArray();
    }

    /**
     * Get the underlying CHIP Purchase object.
     */
    public function getPurchase(): PurchaseData
    {
        return $this->purchase;
    }

    /**
     * Map CHIP status to universal PaymentStatus.
     */
    private function mapChipStatus(string $chipStatus): PaymentStatus
    {
        return match ($chipStatus) {
            'created' => PaymentStatus::CREATED,
            'sent', 'viewed', 'pending_execute', 'pending_charge' => PaymentStatus::PENDING,
            'attempted_capture', 'attempted_refund', 'attempted_recurring', 'pending_refund' => PaymentStatus::PROCESSING,
            'pending_capture' => PaymentStatus::AUTHORIZED,
            'pending_release' => PaymentStatus::AUTHORIZED,
            'hold' => PaymentStatus::AUTHORIZED,
            'preauthorized' => PaymentStatus::AUTHORIZED,
            'paid', 'captured', 'paid_authorized', 'recurring_successful', 'cleared', 'settled' => PaymentStatus::PAID,
            'refunded' => PaymentStatus::REFUNDED,
            'partially_refunded' => PaymentStatus::PARTIALLY_REFUNDED,
            'cancelled', 'released' => PaymentStatus::CANCELLED,
            'expired', 'overdue' => PaymentStatus::EXPIRED,
            'chargeback' => PaymentStatus::DISPUTED,
            'error' => PaymentStatus::FAILED,
            'blocked' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
