<?php

namespace AIArmada\CashierChip;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use LogicException;

/**
 * CHIP Payment Method (Recurring Token) wrapper class.
 *
 * CHIP uses "Recurring Token" for saved payment methods, 
 * similar to Stripe's PaymentMethod.
 */
class PaymentMethod implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The owner of the payment method.
     *
     * @var \AIArmada\CashierChip\Billable|\Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The CHIP recurring token data.
     *
     * @var array
     */
    protected array $recurringToken;

    /**
     * Create a new PaymentMethod instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  array  $recurringToken  The CHIP recurring token data
     * @return void
     */
    public function __construct($owner, array $recurringToken)
    {
        $this->owner = $owner;
        $this->recurringToken = $recurringToken;
    }

    /**
     * Get the recurring token ID.
     *
     * @return string|null
     */
    public function id(): ?string
    {
        return $this->recurringToken['id'] ?? $this->recurringToken['recurring_token'] ?? null;
    }

    /**
     * Get the card brand (if available).
     *
     * @return string|null
     */
    public function brand(): ?string
    {
        return $this->recurringToken['card_brand'] ?? $this->recurringToken['brand'] ?? null;
    }

    /**
     * Get the last four digits of the card (if available).
     *
     * @return string|null
     */
    public function lastFour(): ?string
    {
        return $this->recurringToken['last_4'] ?? $this->recurringToken['card_last_4'] ?? null;
    }

    /**
     * Get the expiration month (if available).
     *
     * @return int|null
     */
    public function expirationMonth(): ?int
    {
        return $this->recurringToken['exp_month'] ?? null;
    }

    /**
     * Get the expiration year (if available).
     *
     * @return int|null
     */
    public function expirationYear(): ?int
    {
        return $this->recurringToken['exp_year'] ?? null;
    }

    /**
     * Get the type of payment method.
     *
     * @return string
     */
    public function type(): string
    {
        return $this->recurringToken['type'] ?? 'card';
    }

    /**
     * Determine if this is the default payment method.
     *
     * @return bool
     */
    public function isDefault(): bool
    {
        $defaultMethod = $this->owner->defaultPaymentMethod();

        return $defaultMethod instanceof self && $this->id() === $defaultMethod->id();
    }

    /**
     * Delete the payment method.
     *
     * @return void
     */
    public function delete(): void
    {
        $this->owner->deletePaymentMethod($this->id());
    }

    /**
     * Get the Eloquent model instance.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Get the underlying recurring token data.
     *
     * @return array
     */
    public function asChipRecurringToken(): array
    {
        return $this->recurringToken;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->recurringToken;
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
     * Dynamically get values from the recurring token data.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->recurringToken[$key] ?? null;
    }
}
