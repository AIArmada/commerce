<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * Contract for payment method representations.
 *
 * @property-read string $id The payment method ID (via id() method)
 */
interface PaymentMethodContract extends Arrayable, Jsonable
{
    /**
     * Get the payment method ID.
     */
    public function id(): string;

    /**
     * Get the gateway name.
     */
    public function gateway(): string;

    /**
     * Get the payment method type (card, bank, etc.).
     */
    public function type(): string;

    /**
     * Get the card brand (if applicable).
     */
    public function brand(): ?string;

    /**
     * Get the last four digits (if applicable).
     */
    public function lastFour(): ?string;

    /**
     * Get the expiration month (if applicable).
     */
    public function expirationMonth(): ?int;

    /**
     * Get the expiration year (if applicable).
     */
    public function expirationYear(): ?int;

    /**
     * Determine if this is the default payment method.
     */
    public function isDefault(): bool;

    /**
     * Delete this payment method.
     */
    public function delete(): void;

    /**
     * Get the owner of this payment method.
     */
    public function owner(): mixed;

    /**
     * Get the underlying gateway payment method object.
     */
    public function asGatewayPaymentMethod(): mixed;
}
