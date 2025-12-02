<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * Discount wrapper for CHIP/Vouchers integration.
 *
 * Represents an applied coupon/voucher discount on a subscription.
 * Provides Stripe-compatible API.
 *
 * @implements Arrayable<string, mixed>
 */
class Discount implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * Create a new Discount instance.
     *
     * @param  array<string, mixed>  $discount
     */
    public function __construct(protected array $discount) {}

    /**
     * Dynamically get values from the discount.
     */
    public function __get(string $key): mixed
    {
        return $this->discount[$key] ?? null;
    }

    /**
     * Get the coupon applied to the discount.
     */
    public function coupon(): ?Coupon
    {
        if (! isset($this->discount['coupon'])) {
            return null;
        }

        if ($this->discount['coupon'] instanceof Coupon) {
            return $this->discount['coupon'];
        }

        // If we have a coupon code, try to retrieve it
        if (is_string($this->discount['coupon'])) {
            return $this->retrieveCoupon($this->discount['coupon']);
        }

        return null;
    }

    /**
     * Get the promotion code applied to create this discount.
     */
    public function promotionCode(): ?PromotionCode
    {
        if (! isset($this->discount['promotion_code'])) {
            return null;
        }

        if ($this->discount['promotion_code'] instanceof PromotionCode) {
            return $this->discount['promotion_code'];
        }

        // If we have a promotion code string, wrap it
        if (is_string($this->discount['promotion_code'])) {
            $coupon = $this->retrieveCoupon($this->discount['promotion_code']);

            if ($coupon) {
                return new PromotionCode($this->discount['promotion_code'], $coupon);
            }
        }

        return null;
    }

    /**
     * Get the date that the coupon was applied.
     */
    public function start(): ?CarbonInterface
    {
        if (isset($this->discount['start'])) {
            if ($this->discount['start'] instanceof CarbonInterface) {
                return $this->discount['start'];
            }

            if (is_int($this->discount['start'])) {
                return Carbon::createFromTimestamp($this->discount['start']);
            }

            if (is_string($this->discount['start'])) {
                return Carbon::parse($this->discount['start']);
            }
        }

        return null;
    }

    /**
     * Get the date that this discount will end.
     */
    public function end(): ?CarbonInterface
    {
        if (isset($this->discount['end'])) {
            if ($this->discount['end'] instanceof CarbonInterface) {
                return $this->discount['end'];
            }

            if (is_int($this->discount['end'])) {
                return Carbon::createFromTimestamp($this->discount['end']);
            }

            if (is_string($this->discount['end'])) {
                return Carbon::parse($this->discount['end']);
            }
        }

        return null;
    }

    /**
     * Get the discount amount applied.
     */
    public function amount(): ?int
    {
        return $this->discount['amount'] ?? null;
    }

    /**
     * Get the formatted discount amount.
     */
    public function formattedAmount(): ?string
    {
        $amount = $this->amount();

        if ($amount === null) {
            return null;
        }

        $currency = $this->discount['currency'] ?? config('cashier-chip.currency', 'MYR');

        return Cashier::formatAmount($amount, $currency);
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'coupon' => $this->coupon()?->toArray(),
            'promotion_code' => $this->promotionCode()?->toArray(),
            'start' => $this->start()?->toIso8601String(),
            'end' => $this->end()?->toIso8601String(),
            'amount' => $this->amount(),
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
     * Retrieve a coupon by its code.
     */
    protected function retrieveCoupon(string $couponId): ?Coupon
    {
        if (! class_exists(\AIArmada\Vouchers\Services\VoucherService::class)) {
            return null;
        }

        /** @var \AIArmada\Vouchers\Services\VoucherService $service */
        $service = app(\AIArmada\Vouchers\Services\VoucherService::class);

        $voucherData = $service->find($couponId);

        if (! $voucherData) {
            return null;
        }

        return new Coupon($voucherData);
    }
}
