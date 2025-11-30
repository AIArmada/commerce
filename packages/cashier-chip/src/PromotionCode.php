<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * PromotionCode wrapper for CHIP/Vouchers integration.
 *
 * Represents a customer-facing promotion code that applies a coupon.
 * Provides Stripe-compatible API.
 *
 * @implements Arrayable<string, mixed>
 */
class PromotionCode implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * Create a new PromotionCode instance.
     */
    public function __construct(
        protected string $code,
        protected Coupon $coupon
    ) {}

    /**
     * Get the promotion code string.
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Get the ID of the promotion code (same as code for vouchers).
     */
    public function id(): string
    {
        return $this->code;
    }

    /**
     * Get the coupon that belongs to the promotion code.
     */
    public function coupon(): Coupon
    {
        return $this->coupon;
    }

    /**
     * Determine if the promotion code is active.
     */
    public function isActive(): bool
    {
        return $this->coupon->isActive();
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'code' => $this->code(),
            'coupon' => $this->coupon()->toArray(),
            'active' => $this->isActive(),
        ];
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options) ?: '';
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Dynamically get values.
     */
    public function __get(string $key): mixed
    {
        return match ($key) {
            'id' => $this->id(),
            'code' => $this->code(),
            'coupon' => $this->coupon(),
            'active' => $this->isActive(),
            default => null,
        };
    }
}
