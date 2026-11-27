<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Data;

use Akaunting\Money\Money;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Voucher data transfer object.
 *
 * Represents a voucher with all its properties and configuration.
 * All monetary values are stored in minor units (cents) as integers.
 * Percentage values are stored in basis points (e.g., 10.50% = 1050).
 */
final readonly class VoucherData implements \JsonSerializable
{
    public ?DateTimeImmutable $startsAt;

    public ?DateTimeImmutable $expiresAt;

    /**
     * @param  int  $value  Value in cents for fixed amounts, or basis points for percentages
     * @param  array<string, mixed>|null  $valueConfig  Configuration for compound voucher types
     * @param  int|null  $minCartValue  Minimum cart value in cents
     * @param  int|null  $maxDiscount  Maximum discount in cents
     * @param  array<string, mixed>|null  $targetDefinition  Target definition for condition application
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     * @param  string|null  $affiliateCommissionType  Override commission type (percentage|fixed)
     * @param  int|null  $affiliateCommissionValue  Override commission rate (basis points or cents)
     * @param  string|null  $affiliateId  Native affiliate linked to the voucher
     * @param  string|null  $affiliateProgramId  Associated affiliate program ID
     * @param  list<array{level: int, type?: string, value?: int, share?: float}>|null  $affiliateUplineLevels  Upline override levels
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public ?string $description,
        public VoucherType $type,
        public int $value,
        public ?array $valueConfig,
        public ?string $creditDestination,
        public int $creditDelayHours,
        public string $currency,
        public ?int $minCartValue,
        public ?int $maxDiscount,
        public ?int $usageLimit,
        public ?int $usageLimitPerUser,
        public bool $allowsManualRedemption,
        public int | string | null $ownerId,
        public ?string $ownerType,
        ?DateTimeInterface $startsAt,
        ?DateTimeInterface $expiresAt,
        public VoucherStatus $status,
        public ?array $targetDefinition,
        public ?array $metadata,
        public ?string $promotionId = null,
        public ?string $affiliateCommissionType = null,
        public ?int $affiliateCommissionValue = null,
        public ?string $affiliateId = null,
        public ?string $affiliateProgramId = null,
        public ?array $affiliateUplineLevels = null,
    ) {
        $this->startsAt = $startsAt === null ? null : DateTimeImmutable::createFromInterface($startsAt);
        $this->expiresAt = $expiresAt === null ? null : DateTimeImmutable::createFromInterface($expiresAt);
    }

    /**
     * Create from an array with sensible defaults.
     *
     * This method provides convenient array-based construction with default values.
     *
     * IMPORTANT: All monetary values must be integers:
     * - `value`: cents for fixed amounts, basis points for percentages (e.g., 1000 = 10%)
     * - `min_cart_value`: cents (e.g., 5000 = $50.00)
     * - `max_discount`: cents (e.g., 10000 = $100.00)
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException If float values are passed where integers are expected
     */
    public static function fromArray(array $data): self
    {
        // Validate integer fields - prevent silent float truncation
        self::validateIntegerField($data, 'value', 'cents for fixed amounts or basis points for percentages');
        self::validateIntegerField($data, 'min_cart_value', 'cents');
        self::validateIntegerField($data, 'minCartValue', 'cents');
        self::validateIntegerField($data, 'max_discount', 'cents');
        self::validateIntegerField($data, 'maxDiscount', 'cents');
        self::validateIntegerField($data, 'credit_delay_hours', 'hours');
        self::validateIntegerField($data, 'creditDelayHours', 'hours');
        self::validateIntegerField($data, 'usage_limit', 'uses');
        self::validateIntegerField($data, 'usageLimit', 'uses');
        self::validateIntegerField($data, 'usage_limit_per_user', 'uses per user');
        self::validateIntegerField($data, 'usageLimitPerUser', 'uses per user');
        self::validateIntegerField($data, 'affiliate_commission_value', 'basis points or cents');
        self::validateIntegerField($data, 'affiliateCommissionValue', 'basis points or cents');
        self::validateAffiliateUplineLevels($data['affiliate_upline_levels'] ?? $data['affiliateUplineLevels'] ?? null);

        // Normalize enums if passed as strings
        $type = $data['type'] ?? VoucherType::Fixed;
        if (is_string($type)) {
            $type = VoucherType::from($type);
        }

        $status = $data['status'] ?? VoucherStatus::Active;
        $status = $status instanceof VoucherStatus ? $status : VoucherStatus::from($status);

        // Get currency with config fallback
        $currency = $data['currency'] ?? (string) config('vouchers.default_currency', 'MYR');

        // Convert date strings to DateTime objects
        $startsAt = $data['starts_at'] ?? $data['startsAt'] ?? null;
        if (is_string($startsAt)) {
            $startsAt = new DateTimeImmutable($startsAt);
        }

        $expiresAt = $data['expires_at'] ?? $data['expiresAt'] ?? null;
        if (is_string($expiresAt)) {
            $expiresAt = new DateTimeImmutable($expiresAt);
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            description: $data['description'] ?? null,
            type: $type,
            value: (int) ($data['value'] ?? 0),
            valueConfig: $data['value_config'] ?? $data['valueConfig'] ?? null,
            creditDestination: $data['credit_destination'] ?? $data['creditDestination'] ?? null,
            creditDelayHours: (int) ($data['credit_delay_hours'] ?? $data['creditDelayHours'] ?? 0),
            currency: $currency,
            minCartValue: isset($data['min_cart_value']) || isset($data['minCartValue'])
                ? (int) ($data['min_cart_value'] ?? $data['minCartValue'])
                : null,
            maxDiscount: isset($data['max_discount']) || isset($data['maxDiscount'])
                ? (int) ($data['max_discount'] ?? $data['maxDiscount'])
                : null,
            usageLimit: $data['usage_limit'] ?? $data['usageLimit'] ?? null,
            usageLimitPerUser: $data['usage_limit_per_user'] ?? $data['usageLimitPerUser'] ?? null,
            allowsManualRedemption: (bool) ($data['allows_manual_redemption'] ?? $data['allowsManualRedemption'] ?? false),
            ownerId: $data['owner_id'] ?? $data['ownerId'] ?? null,
            ownerType: $data['owner_type'] ?? $data['ownerType'] ?? null,
            startsAt: $startsAt,
            expiresAt: $expiresAt,
            status: $status,
            targetDefinition: $data['target_definition'] ?? $data['targetDefinition'] ?? null,
            metadata: $data['metadata'] ?? null,
            promotionId: $data['promotion_id'] ?? $data['promotionId'] ?? null,
            affiliateCommissionType: self::normalizeAffiliateCommissionType(
                $data['affiliate_commission_type'] ?? $data['affiliateCommissionType'] ?? null
            ),
            affiliateCommissionValue: $data['affiliate_commission_value'] ?? $data['affiliateCommissionValue'] ?? null,
            affiliateId: $data['affiliate_id'] ?? $data['affiliateId'] ?? null,
            affiliateProgramId: $data['affiliate_program_id'] ?? $data['affiliateProgramId'] ?? null,
            affiliateUplineLevels: $data['affiliate_upline_levels'] ?? $data['affiliateUplineLevels'] ?? null,
        );
    }

    /**
     * Check if the voucher is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === VoucherStatus::Active;
    }

    /**
     * Check if the voucher is a percentage discount.
     */
    public function isPercentage(): bool
    {
        return $this->type === VoucherType::Percentage;
    }

    /**
     * Check if the voucher is a fixed amount discount.
     */
    public function isFixed(): bool
    {
        return $this->type === VoucherType::Fixed;
    }

    /**
     * Check if the voucher provides free shipping.
     */
    public function isFreeShipping(): bool
    {
        return $this->type === VoucherType::FreeShipping;
    }

    /**
     * Check if the voucher is a compound type.
     */
    public function isCompound(): bool
    {
        return $this->type->isCompound();
    }

    /**
     * Check if the voucher has expired.
     */
    public function hasExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < CarbonImmutable::now();
    }

    /**
     * Check if the voucher has started.
     */
    public function hasStarted(): bool
    {
        if ($this->startsAt === null) {
            return true;
        }

        return $this->startsAt <= CarbonImmutable::now();
    }

    /**
     * Check if the voucher is within its valid date range.
     */
    public function isWithinDateRange(): bool
    {
        return $this->hasStarted() && ! $this->hasExpired();
    }

    /**
     * Check if the voucher has a minimum cart value requirement.
     */
    public function hasMinCartValue(): bool
    {
        return $this->minCartValue !== null && $this->minCartValue > 0;
    }

    /**
     * Check if a cart value meets the minimum requirement.
     *
     * @param  int  $cartValue  Cart value in minor units (cents)
     */
    public function meetsMinCartValue(int $cartValue): bool
    {
        if (! $this->hasMinCartValue()) {
            return true;
        }

        return $cartValue >= $this->minCartValue;
    }

    /**
     * Get the formatted value for display.
     */
    public function getFormattedValue(): string
    {
        if ($this->isPercentage()) {
            $percentage = $this->value / 100;

            return number_format($percentage, 2) . '%';
        }

        $money = Money::{$this->currency}($this->value);

        return (string) $money;
    }

    /**
     * Validate that a field contains an integer value.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException If a float is passed
     */
    private static function validateIntegerField(array $data, string $field, string $description): void
    {
        if (! isset($data[$field])) {
            return;
        }

        $value = $data[$field];

        if (is_float($value)) {
            throw new \InvalidArgumentException("VoucherData '{$field}' must be an integer ({$description}). Got float: {$value}.");
        }
    }

    /**
     * Validate integer upline fields while preserving the affiliate contract
     * that represents commission shares as decimal ratios.
     */
    private static function validateAffiliateUplineLevels(mixed $levels): void
    {
        if (! is_array($levels)) {
            return;
        }

        foreach ($levels as $index => $level) {
            if (! is_array($level)) {
                continue;
            }

            foreach (['level' => 'level', 'value' => 'cents or basis points'] as $field => $description) {
                $value = $level[$field] ?? null;

                if (is_float($value)) {
                    throw new \InvalidArgumentException("VoucherData affiliate_upline_levels.{$index}.{$field} must be an integer ({$description}).");
                }
            }
        }
    }

    private static function normalizeAffiliateCommissionType(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'value' => $this->value,
            'value_config' => $this->valueConfig,
            'credit_destination' => $this->creditDestination,
            'credit_delay_hours' => $this->creditDelayHours,
            'currency' => $this->currency,
            'min_cart_value' => $this->minCartValue,
            'max_discount' => $this->maxDiscount,
            'usage_limit' => $this->usageLimit,
            'usage_limit_per_user' => $this->usageLimitPerUser,
            'allows_manual_redemption' => $this->allowsManualRedemption,
            'owner_id' => $this->ownerId,
            'owner_type' => $this->ownerType,
            'starts_at' => $this->startsAt?->format(DATE_ATOM),
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
            'status' => $this->status->value,
            'target_definition' => $this->targetDefinition,
            'metadata' => $this->metadata,
            'promotion_id' => $this->promotionId,
            'affiliate_commission_type' => $this->affiliateCommissionType,
            'affiliate_commission_value' => $this->affiliateCommissionValue,
            'affiliate_id' => $this->affiliateId,
            'affiliate_program_id' => $this->affiliateProgramId,
            'affiliate_upline_levels' => $this->affiliateUplineLevels,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }
}
