<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\Chip\DataObjects\Purchase;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\ForwardsCalls;
use JsonSerializable;
use ReturnTypeWillChange;

/**
 * CHIP Payment (Purchase) wrapper class.
 *
 * CHIP uses "Purchase" as its payment object, similar to Stripe's PaymentIntent.
 */
class Payment implements Arrayable, Jsonable, JsonSerializable
{
    use ForwardsCalls;

    /**
     * The status for a successful purchase.
     */
    public const STATUS_SUCCESS = 'success';

    /**
     * The status for a pending purchase.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * The status for an expired purchase.
     */
    public const STATUS_EXPIRED = 'expired';

    /**
     * The status for a failed purchase.
     */
    public const STATUS_FAILED = 'failed';

    /**
     * The status for a cancelled purchase.
     */
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The status for a refunded purchase.
     */
    public const STATUS_REFUNDED = 'refunded';

    /**
     * The related customer instance.
     *
     * @var Billable|null
     */
    protected $customer;

    /**
     * The CHIP purchase instance.
     */
    protected Purchase $purchase;

    /**
     * Create a new Payment instance.
     */
    public function __construct(Purchase $purchase)
    {
        $this->purchase = $purchase;
    }

    /**
     * Dynamically get values from the purchase.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->purchase->{$key} ?? null;
    }

    /**
     * Get the purchase ID.
     */
    public function id(): string
    {
        return $this->purchase->id;
    }

    /**
     * Get the total amount that will be paid (formatted).
     */
    public function amount(): string
    {
        return CashierChip::formatAmount($this->rawAmount(), $this->currency());
    }

    /**
     * Get the raw total amount that will be paid (in cents/minor units).
     */
    public function rawAmount(): int
    {
        return $this->purchase->getAmountInCents();
    }

    /**
     * Get the currency.
     */
    public function currency(): string
    {
        return $this->purchase->getCurrency();
    }

    /**
     * Get the checkout URL for completing the payment.
     */
    public function checkoutUrl(): ?string
    {
        return $this->purchase->getCheckoutUrl();
    }

    /**
     * Get the status of the purchase.
     */
    public function status(): string
    {
        return $this->purchase->status;
    }

    /**
     * Determine if the payment is successful.
     */
    public function isSucceeded(): bool
    {
        return $this->status() === self::STATUS_SUCCESS;
    }

    /**
     * Determine if the payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status() === self::STATUS_PENDING;
    }

    /**
     * Determine if the payment has expired.
     */
    public function isExpired(): bool
    {
        return $this->status() === self::STATUS_EXPIRED;
    }

    /**
     * Determine if the payment has failed.
     */
    public function isFailed(): bool
    {
        return $this->status() === self::STATUS_FAILED;
    }

    /**
     * Determine if the payment was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status() === self::STATUS_CANCELLED;
    }

    /**
     * Determine if the payment was refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status() === self::STATUS_REFUNDED;
    }

    /**
     * Determine if the payment requires a redirect to checkout.
     */
    public function requiresRedirect(): bool
    {
        return $this->isPending() && ! empty($this->checkoutUrl());
    }

    /**
     * Get the recurring token from this purchase (if available).
     */
    public function recurringToken(): ?string
    {
        return $this->purchase->recurring_token;
    }

    /**
     * Validate if the payment was successful and throw an exception if not.
     *
     * @throws IncompletePayment
     */
    public function validate(): void
    {
        if ($this->requiresRedirect()) {
            throw IncompletePayment::requiresRedirect($this);
        }
        if ($this->isFailed()) {
            throw IncompletePayment::failed($this);
        }
        if ($this->isExpired()) {
            throw IncompletePayment::expired($this);
        }
    }

    /**
     * Retrieve the related customer for the payment if one exists.
     *
     * @return Billable|null
     */
    public function customer()
    {
        if ($this->customer) {
            return $this->customer;
        }

        $clientId = $this->purchase->getClientId();

        if ($clientId) {
            return $this->customer = CashierChip::findBillable($clientId);
        }

        return null;
    }

    /**
     * Set the customer instance.
     *
     * @param  Billable  $customer
     * @return $this
     */
    public function setCustomer($customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    /**
     * Get the underlying CHIP Purchase DataObject.
     */
    public function asChipPurchase(): Purchase
    {
        return $this->purchase;
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->purchase->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<string, mixed>
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
