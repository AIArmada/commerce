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
        return $this->purchase->isPaid() || $this->purchase->marked_as_paid;
    }

    public function isPending(): bool
    {
        return $this->purchase->isPending() || $this->purchase->status === 'created';
    }

    public function isFailed(): bool
    {
        return $this->purchase->hasError();
    }

    public function isCancelled(): bool
    {
        return $this->purchase->isCancelled();
    }

    public function isRefunded(): bool
    {
        return $this->purchase->isRefunded();
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
            'pending_execute' => PaymentStatus::PENDING,
            'pending_charge' => PaymentStatus::PENDING,
            'pending_capture' => PaymentStatus::AUTHORIZED,
            'pending_release' => PaymentStatus::AUTHORIZED,
            'pending_refund' => PaymentStatus::PROCESSING,
            'hold' => PaymentStatus::AUTHORIZED,
            'preauthorized' => PaymentStatus::AUTHORIZED,
            'paid' => PaymentStatus::PAID,
            'refunded' => PaymentStatus::REFUNDED,
            'partially_refunded' => PaymentStatus::PARTIALLY_REFUNDED,
            'cancelled' => PaymentStatus::CANCELLED,
            'expired' => PaymentStatus::EXPIRED,
            'error' => PaymentStatus::FAILED,
            'blocked' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
