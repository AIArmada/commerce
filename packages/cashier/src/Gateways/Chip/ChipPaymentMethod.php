<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\PaymentMethodContract;

/**
 * Wrapper for CHIP payment method (recurring token).
 */
class ChipPaymentMethod implements PaymentMethodContract
{
    /**
     * Create a new CHIP payment method wrapper.
     *
     * @param  array<string, mixed>  $token
     */
    public function __construct(
        protected array $token,
        protected ?BillableContract $billable = null
    ) {}

    /**
     * Get the payment method ID (recurring token).
     */
    public function id(): string
    {
        return $this->token['recurring_token'] ?? $this->token['id'] ?? '';
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'chip';
    }

    /**
     * Get the card brand.
     */
    public function brand(): ?string
    {
        return $this->token['card_brand'] ?? $this->token['brand'] ?? null;
    }

    /**
     * Get the last four digits.
     */
    public function lastFour(): ?string
    {
        return $this->token['card_last4'] ?? $this->token['last4'] ?? null;
    }

    /**
     * Get the expiration month.
     */
    public function expirationMonth(): ?int
    {
        $exp = $this->token['card_expiry'] ?? null;
        if ($exp && preg_match('/^(\d{2})\/\d{2}$/', $exp, $matches)) {
            return (int) $matches[1];
        }

        return $this->token['exp_month'] ?? null;
    }

    /**
     * Get the expiration year.
     */
    public function expirationYear(): ?int
    {
        $exp = $this->token['card_expiry'] ?? null;
        if ($exp && preg_match('/^\d{2}\/(\d{2})$/', $exp, $matches)) {
            $year = (int) $matches[1];

            return $year + 2000; // Convert 2-digit to 4-digit year
        }

        return $this->token['exp_year'] ?? null;
    }

    /**
     * Get the payment method type.
     */
    public function type(): string
    {
        return $this->token['payment_method'] ?? $this->token['type'] ?? 'card';
    }

    /**
     * Determine if this is the default payment method.
     */
    public function isDefault(): bool
    {
        if (! $this->billable) {
            return false;
        }

        return $this->billable->defaultPaymentMethod() === $this->id();
    }

    /**
     * Get the owner.
     */
    public function owner(): ?BillableContract
    {
        return $this->billable;
    }

    /**
     * Delete the payment method.
     */
    public function delete(): bool
    {
        if (! $this->billable) {
            return false;
        }

        try {
            $this->billable->deletePaymentMethod($this->id());

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get the underlying payment method data.
     *
     * @return array<string, mixed>
     */
    public function asGatewayPaymentMethod(): array
    {
        return $this->token;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'gateway' => $this->gateway(),
            'type' => $this->type(),
            'brand' => $this->brand(),
            'last_four' => $this->lastFour(),
            'expiration_month' => $this->expirationMonth(),
            'expiration_year' => $this->expirationYear(),
            'is_default' => $this->isDefault(),
        ];
    }
}
