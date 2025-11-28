<?php

namespace AIArmada\CashierChip;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\ForwardsCalls;
use JsonSerializable;
use AIArmada\CashierChip\Exceptions\IncompletePayment;

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
     * @var \AIArmada\CashierChip\Billable|null
     */
    protected $customer;

    /**
     * The CHIP purchase data.
     *
     * @var array
     */
    protected array $purchase;

    /**
     * Create a new Payment instance.
     *
     * @param  array  $purchase  The CHIP purchase response data
     * @return void
     */
    public function __construct(array $purchase)
    {
        $this->purchase = $purchase;
    }

    /**
     * Get the purchase ID.
     *
     * @return string|null
     */
    public function id(): ?string
    {
        return $this->purchase['id'] ?? null;
    }

    /**
     * Get the total amount that will be paid.
     *
     * @return string
     */
    public function amount(): string
    {
        return CashierChip::formatAmount($this->rawAmount(), $this->currency());
    }

    /**
     * Get the raw total amount that will be paid.
     *
     * @return int
     */
    public function rawAmount(): int
    {
        // CHIP returns amount in decimal, convert to cents
        $amount = $this->purchase['purchase']['total'] ?? $this->purchase['amount'] ?? 0;
        
        return (int) ($amount * 100);
    }

    /**
     * Get the currency.
     *
     * @return string
     */
    public function currency(): string
    {
        return $this->purchase['purchase']['currency'] ?? $this->purchase['currency'] ?? config('cashier-chip.currency', 'MYR');
    }

    /**
     * Get the checkout URL for completing the payment.
     *
     * @return string|null
     */
    public function checkoutUrl(): ?string
    {
        return $this->purchase['checkout_url'] ?? null;
    }

    /**
     * Get the status of the purchase.
     *
     * @return string|null
     */
    public function status(): ?string
    {
        return $this->purchase['status'] ?? null;
    }

    /**
     * Determine if the payment is successful.
     *
     * @return bool
     */
    public function isSucceeded(): bool
    {
        return $this->status() === self::STATUS_SUCCESS;
    }

    /**
     * Determine if the payment is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status() === self::STATUS_PENDING;
    }

    /**
     * Determine if the payment has expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->status() === self::STATUS_EXPIRED;
    }

    /**
     * Determine if the payment has failed.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status() === self::STATUS_FAILED;
    }

    /**
     * Determine if the payment was cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->status() === self::STATUS_CANCELLED;
    }

    /**
     * Determine if the payment was refunded.
     *
     * @return bool
     */
    public function isRefunded(): bool
    {
        return $this->status() === self::STATUS_REFUNDED;
    }

    /**
     * Determine if the payment requires a redirect to checkout.
     *
     * @return bool
     */
    public function requiresRedirect(): bool
    {
        return $this->isPending() && ! empty($this->checkoutUrl());
    }

    /**
     * Get the recurring token from this purchase (if available).
     *
     * @return string|null
     */
    public function recurringToken(): ?string
    {
        return $this->purchase['recurring_token'] ?? null;
    }

    /**
     * Validate if the payment was successful and throw an exception if not.
     *
     * @return void
     *
     * @throws \AIArmada\CashierChip\Exceptions\IncompletePayment
     */
    public function validate(): void
    {
        if ($this->requiresRedirect()) {
            throw IncompletePayment::requiresRedirect($this);
        } elseif ($this->isFailed()) {
            throw IncompletePayment::failed($this);
        } elseif ($this->isExpired()) {
            throw IncompletePayment::expired($this);
        }
    }

    /**
     * Retrieve the related customer for the payment if one exists.
     *
     * @return \AIArmada\CashierChip\Billable|null
     */
    public function customer()
    {
        if ($this->customer) {
            return $this->customer;
        }

        $clientId = $this->purchase['client']['id'] ?? $this->purchase['client_id'] ?? null;

        if ($clientId) {
            return $this->customer = CashierChip::findBillable($clientId);
        }

        return null;
    }

    /**
     * Set the customer instance.
     *
     * @param  \AIArmada\CashierChip\Billable  $customer
     * @return $this
     */
    public function setCustomer($customer)
    {
        $this->customer = $customer;

        return $this;
    }

    /**
     * Get the underlying purchase data.
     *
     * @return array
     */
    public function asChipPurchase(): array
    {
        return $this->purchase;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->purchase;
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Dynamically get values from the purchase data.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->purchase[$key] ?? null;
    }
}
