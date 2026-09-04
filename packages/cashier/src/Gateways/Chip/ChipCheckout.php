<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\PurchaseStatus;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Illuminate\Http\RedirectResponse;

/**
 * Wrapper for CHIP checkout session (purchase).
 */
class ChipCheckout implements CheckoutContract
{
    /**
     * Create a new CHIP checkout wrapper.
     */
    public function __construct(
        protected PurchaseData $purchase
    ) {}

    /**
     * Get the checkout session ID.
     */
    public function id(): string
    {
        return $this->purchase->id;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'chip';
    }

    /**
     * Get the checkout URL.
     */
    public function url(): string
    {
        return $this->purchase->getCheckoutUrl() ?? '';
    }

    /**
     * Redirect to the checkout page.
     */
    public function redirect(): RedirectResponse
    {
        return redirect()->to($this->url());
    }

    /**
     * Get the success URL.
     */
    public function successUrl(): string
    {
        return $this->purchase->success_redirect ?? '';
    }

    /**
     * Get the cancel URL.
     */
    public function cancelUrl(): string
    {
        return $this->purchase->cancel_redirect ?? $this->purchase->failure_redirect ?? '';
    }

    /**
     * Get the checkout status.
     */
    public function status(): string
    {
        return $this->purchase->status;
    }

    /**
     * Get the payment status.
     */
    public function paymentStatus(): string
    {
        return match ($this->purchaseStatus()) {
            PurchaseStatus::PAID,
            PurchaseStatus::CLEARED,
            PurchaseStatus::SETTLED => 'paid',
            PurchaseStatus::CREATED,
            PurchaseStatus::SENT,
            PurchaseStatus::VIEWED,
            PurchaseStatus::OVERDUE,
            PurchaseStatus::PENDING_EXECUTE,
            PurchaseStatus::PENDING_CAPTURE,
            PurchaseStatus::PENDING_CHARGE,
            PurchaseStatus::PENDING_RELEASE,
            PurchaseStatus::PENDING_REFUND => 'unpaid',
            PurchaseStatus::EXPIRED => 'expired',
            PurchaseStatus::CANCELLED,
            PurchaseStatus::RELEASED => 'cancelled',
            PurchaseStatus::ERROR,
            PurchaseStatus::BLOCKED,
            PurchaseStatus::CHARGEBACK => 'failed',
            PurchaseStatus::REFUNDED => 'refunded',
            PurchaseStatus::HOLD,
            PurchaseStatus::PREAUTHORIZED => 'authorized',
        };
    }

    /**
     * Determine if the checkout is complete.
     */
    public function isComplete(): bool
    {
        return $this->purchaseStatus()->isSuccessful();
    }

    /**
     * Determine if the checkout was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->purchaseStatus()->isSuccessful();
    }

    /**
     * Determine if the checkout is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->purchaseStatus(), [
            PurchaseStatus::CREATED,
            PurchaseStatus::SENT,
            PurchaseStatus::VIEWED,
            PurchaseStatus::OVERDUE,
            PurchaseStatus::PENDING_EXECUTE,
            PurchaseStatus::PENDING_CAPTURE,
            PurchaseStatus::PENDING_CHARGE,
            PurchaseStatus::PENDING_RELEASE,
            PurchaseStatus::PENDING_REFUND,
        ]);
    }

    /**
     * Determine if the checkout has expired.
     */
    public function isExpired(): bool
    {
        return $this->purchaseStatus() === PurchaseStatus::EXPIRED;
    }

    /**
     * Get the total amount in cents.
     */
    public function rawTotal(): int
    {
        // Use the Purchase's nested PurchaseDetails object
        return $this->purchase->purchase->getTotalInCents();
    }

    /**
     * Get the formatted total.
     */
    public function total(): string
    {
        return MoneyFormatter::formatMinorWithCode($this->rawTotal(), $this->currency());
    }

    /**
     * Get the currency.
     */
    public function currency(): string
    {
        return mb_strtoupper($this->purchase->purchase->currency);
    }

    /**
     * Get the customer if available.
     */
    public function customer(): ?CustomerContract
    {
        // Would need to resolve from CHIP
        return null;
    }

    /**
     * Get the recurring token from this purchase (if available).
     */
    public function recurringToken(): ?string
    {
        return $this->purchase->recurring_token;
    }

    /**
     * Get the underlying gateway checkout object.
     */
    public function asGatewayCheckout(): PurchaseData
    {
        return $this->purchase;
    }

    private function purchaseStatus(): PurchaseStatus
    {
        return PurchaseStatus::from($this->purchase->status);
    }
}
