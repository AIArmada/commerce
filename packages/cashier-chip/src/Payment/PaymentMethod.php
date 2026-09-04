<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Payment;

use AIArmada\CashierChip\Contracts\BillableContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use ReturnTypeWillChange;

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
     * @phpstan-var Model&BillableContract
     */
    protected $owner;

    /**
     * The CHIP recurring token data.
     */
    protected array $recurringToken;

    protected ?StoredPaymentMethod $storedPaymentMethod = null;

    /**
     * Create a new PaymentMethod instance.
     *
     * @param  array|StoredPaymentMethod  $recurringToken  The stored payment method or CHIP recurring token resource
     * @return void
     */
    /**
     * @phpstan-param Model&BillableContract $owner
     */
    public function __construct(Model $owner, array | StoredPaymentMethod $recurringToken)
    {
        $this->owner = $owner;

        if ($recurringToken instanceof StoredPaymentMethod) {
            $this->storedPaymentMethod = $recurringToken;
            $this->recurringToken = [
                'id' => $recurringToken->recurringToken(),
                'payment_method' => $recurringToken->type,
                'description' => data_get($recurringToken->metadata, 'description'),
            ];

            return;
        }

        $this->recurringToken = $recurringToken;
    }

    /**
     * Dynamically get values from the recurring token data.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->recurringToken[$key] ?? null;
    }

    /**
     * Get the recurring token ID.
     */
    public function id(): ?string
    {
        $id = $this->storedPaymentMethod?->recurringToken() ?? ($this->recurringToken['id'] ?? null);

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Get the card brand when the local payment-method record has one.
     */
    public function brand(): ?string
    {
        return $this->storedPaymentMethod?->brand;
    }

    /**
     * Get the last four digits when the local payment-method record has them.
     */
    public function lastFour(): ?string
    {
        return $this->storedPaymentMethod?->last_four;
    }

    /**
     * Get the expiration month (if available).
     */
    public function expirationMonth(): ?int
    {
        return null;
    }

    /**
     * Get the expiration year (if available).
     */
    public function expirationYear(): ?int
    {
        return null;
    }

    /**
     * Get the card brand.
     */
    public function cardBrand(): ?string
    {
        return $this->brand();
    }

    /**
     * Get the last four digits.
     */
    public function cardLastFour(): ?string
    {
        return $this->lastFour();
    }

    /**
     * Get the expiration month.
     */
    public function cardExpMonth(): ?int
    {
        return $this->expirationMonth();
    }

    /**
     * Get the expiration year.
     */
    public function cardExpYear(): ?int
    {
        return $this->expirationYear();
    }

    /**
     * Get the CHIP recurring token identifier.
     */
    public function chipToken(): ?string
    {
        return $this->id();
    }

    /**
     * Get the type of payment method.
     */
    public function type(): string
    {
        $type = $this->storedPaymentMethod?->type ?? ($this->recurringToken['payment_method'] ?? null);

        return is_string($type) ? $type : '';
    }

    /**
     * Determine if this is the default payment method.
     */
    public function isDefault(): bool
    {
        $defaultMethod = $this->owner->defaultPaymentMethod();

        return $defaultMethod instanceof self && $this->id() === $defaultMethod->id();
    }

    /**
     * Delete the payment method.
     */
    public function delete(): void
    {
        $paymentMethodId = $this->id();

        if ($paymentMethodId === null) {
            return;
        }

        $this->owner->deletePaymentMethod($paymentMethodId);
    }

    /**
     * Get the Eloquent model instance.
     *
     * @return Model
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Get the underlying recurring token data.
     */
    public function asChipRecurringToken(): array
    {
        if ($this->storedPaymentMethod instanceof StoredPaymentMethod) {
            return [
                'id' => $this->storedPaymentMethod->recurringToken(),
                'payment_method' => $this->storedPaymentMethod->type,
                'description' => data_get($this->storedPaymentMethod->metadata, 'description'),
            ];
        }

        return $this->recurringToken;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'type' => $this->type(),
            'brand' => $this->brand(),
            'last_4' => $this->lastFour(),
            'is_default' => $this->isDefault(),
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
