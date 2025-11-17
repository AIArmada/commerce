<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Models;

use AIArmada\FilamentVouchers\Support\OwnerTypeRegistry;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher as BaseVoucher;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property float|null $usageProgress
 * @property string|null $end_date
 * @property int|null $walletEntriesCount
 * @property int|null $walletClaimedCount
 * @property int|null $walletRedeemedCount
 * @property int|null $walletAvailableCount
 */
final class Voucher extends BaseVoucher
{
    /**
     * Provides a human-readable representation of the polymorphic owner using
     * the configured owner registry.
     */
    protected function ownerDisplayName(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $owner = $this->owner;

                if (! $owner) {
                    return null;
                }

                return app(OwnerTypeRegistry::class)->resolveDisplayLabel($owner);
            }
        );
    }

    /**
     * Returns the percentage of the global usage limit that has been consumed.
     */
    protected function usageProgress(): Attribute
    {
        return Attribute::make(
            get: function (): ?float {
                $usageLimit = $this->getAttribute('usage_limit');

                if (! $usageLimit || $usageLimit <= 0) {
                    return null;
                }

                $timesUsed = (int) $this->getAttribute('times_used');

                return min(100, ($timesUsed / $usageLimit) * 100);
            }
        );
    }

    /**
     * Alias for expires_at for backward compatibility.
     */
    protected function endDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->expires_at,
        );
    }

    protected function valueLabel(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $value = (int) $this->getAttribute('value');
                $type = $this->getAttribute('type');

                $enumType = $type instanceof VoucherType ? $type : VoucherType::tryFrom((string) $type);

                if ($enumType === VoucherType::Percentage) {
                    // Value is stored in basis points (e.g., 1000 = 10.00%, 1259 = 12.59%)
                    $percentage = $value / 100;

                    return mb_rtrim(mb_rtrim(number_format($percentage, 2), '0'), '.').' %';
                }

                // Value is stored as cents
                $currency = mb_strtoupper((string) ($this->getAttribute('currency') ?? config('filament-vouchers.default_currency', 'MYR')));

                return (string) Money::{$currency}($value);
            }
        );
    }

    /**
     * Returns the total number of wallet entries for this voucher.
     */
    protected function walletEntriesCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->walletEntries()->count()
        );
    }

    /**
     * Returns the number of claimed wallet entries.
     */
    protected function walletClaimedCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->walletEntries()->where('is_claimed', true)->count()
        );
    }

    /**
     * Returns the number of redeemed wallet entries.
     */
    protected function walletRedeemedCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->walletEntries()->where('is_redeemed', true)->count()
        );
    }

    /**
     * Returns the number of available (not redeemed) wallet entries.
     */
    protected function walletAvailableCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->walletEntries()->where('is_redeemed', false)->count()
        );
    }
}
