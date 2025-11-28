<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property VoucherType $type
 * @property int $value Value in cents for fixed amounts, or basis points for percentage (e.g., 10.50% = 1050)
 * @property string $currency
 * @property int|null $min_cart_value Value in cents
 * @property int|null $max_discount Value in cents
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_user
 * @property int $applied_count Number of times the voucher has been applied to carts
 * @property bool $allows_manual_redemption
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property VoucherStatus $status
 * @property array<string, mixed>|null $target_definition
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $times_used
 * @property-read float|null $usageProgress
 * @property-read string|null $owner_display_name
 * @property-read int|null $remaining_uses
 * @property-read string $value_label
 * @property-read int $wallet_entries_count
 * @property-read int $wallet_claimed_count
 * @property-read int $wallet_redeemed_count
 * @property-read int $wallet_available_count
 */
class Voucher extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'currency',
        'min_cart_value',
        'max_discount',
        'usage_limit',
        'usage_limit_per_user',
        'applied_count',
        'allows_manual_redemption',
        'starts_at',
        'expires_at',
        'status',
        'metadata',
        'owner_type',
        'owner_id',
        'target_definition',
    ];

    public function getTable(): string
    {
        /** @var string $table */
        $table = config('vouchers.table_names.vouchers', 'vouchers');

        return $table;
    }

    public function usages(): HasMany
    {
        /** @var HasMany<VoucherUsage, Voucher> $relation */
        $relation = $this->hasMany(VoucherUsage::class);

        return $relation;
    }

    public function walletEntries(): HasMany
    {
        /** @var HasMany<VoucherWallet, Voucher> $relation */
        $relation = $this->hasMany(VoucherWallet::class);

        return $relation;
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true): Builder
    {
        if (! config('vouchers.owner.enabled', false)) {
            return $query;
        }

        if (! $owner) {
            return $includeGlobal
                ? $query->whereNull('owner_id')
                : $query->whereNull('owner_type')->whereNull('owner_id');
        }

        return $query->where(function (Builder $builder) use ($owner, $includeGlobal): void {
            $builder->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (Builder $inner): void {
                    $inner->whereNull('owner_type')->whereNull('owner_id');
                });
            }
        });
    }

    public function allowsManualRedemption(): bool
    {
        return (bool) $this->getAttribute('allows_manual_redemption');
    }

    public function isActive(): bool
    {
        /** @var VoucherStatus|null $status */
        $status = $this->getAttribute('status');

        return $status === VoucherStatus::Active;
    }

    public function isExpired(): bool
    {
        /** @var \Illuminate\Support\Carbon|null $expiresAt */
        $expiresAt = $this->getAttribute('expires_at');

        return $expiresAt !== null && $expiresAt->isPast();
    }

    public function hasStarted(): bool
    {
        /** @var \Illuminate\Support\Carbon|null $startsAt */
        $startsAt = $this->getAttribute('starts_at');

        return $startsAt === null || $startsAt->isPast();
    }

    public function hasUsageLimitRemaining(): bool
    {
        $usageLimit = $this->getAttribute('usage_limit');

        if (! $usageLimit) {
            return true;
        }

        return $this->times_used < $usageLimit;
    }

    public function getRemainingUses(): ?int
    {
        /** @var int|null $usageLimit */
        $usageLimit = $this->getAttribute('usage_limit');

        if ($usageLimit === null) {
            return null;
        }

        return max(0, $usageLimit - $this->times_used);
    }

    public function incrementUsage(): void
    {
        // Auto-update status if depleted
        $usageLimit = $this->getAttribute('usage_limit');

        if ($usageLimit && $this->usages()->count() >= $usageLimit) {
            $this->update(['status' => VoucherStatus::Depleted]);
        }
    }

    /**
     * Get the conversion rate (redeemed vs applied).
     * Returns the percentage of applications that resulted in actual usage/redemption.
     *
     * @return float|null Conversion rate as percentage (0-100), or null if never applied
     */
    public function getConversionRate(): ?float
    {
        /** @var int $appliedCount */
        $appliedCount = $this->getAttribute('applied_count') ?? 0;

        if ($appliedCount === 0) {
            return null;
        }

        $usageCount = $this->times_used;

        return ($usageCount / $appliedCount) * 100;
    }

    /**
     * Get the number of times this voucher was applied but not redeemed.
     *
     * @return int Number of abandoned applications
     */
    public function getAbandonedCount(): int
    {
        /** @var int $appliedCount */
        $appliedCount = $this->getAttribute('applied_count') ?? 0;
        $usageCount = $this->times_used;

        return (int) max(0, $appliedCount - $usageCount);
    }

    /**
     * Get comprehensive statistics for this voucher.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        /** @var int $appliedCount */
        $appliedCount = $this->getAttribute('applied_count') ?? 0;
        $usageCount = $this->times_used;

        return [
            'applied_count' => $appliedCount,
            'redeemed_count' => $usageCount,
            'abandoned_count' => $this->getAbandonedCount(),
            'conversion_rate' => $this->getConversionRate(),
            'remaining_uses' => $this->getRemainingUses(),
        ];
    }

    public function getTimesUsedAttribute(): int
    {
        /** @var int|null $usagesCount */
        $usagesCount = $this->getAttribute('usages_count');

        return $usagesCount ?? $this->usages()->count();
    }

    public function canBeRedeemed(): bool
    {
        return $this->isActive()
            && $this->hasStarted()
            && ! $this->isExpired()
            && $this->hasUsageLimitRemaining();
    }

    public function getUsageProgressAttribute(): ?float
    {
        /** @var int|null $usageLimit */
        $usageLimit = $this->getAttribute('usage_limit');

        if (! $usageLimit) {
            return null;
        }

        $timesUsed = $this->getTimesUsedAttribute();

        return min(100, ($timesUsed / $usageLimit) * 100);
    }

    public function getOwnerDisplayNameAttribute(): ?string
    {
        $owner = $this->owner;

        if (! $owner) {
            return null;
        }

        if (method_exists($owner, 'getAttribute')) {
            /** @var string|null $name */
            $name = $owner->getAttribute('name');
            /** @var string|null $displayName */
            $displayName = $owner->getAttribute('display_name');
            /** @var string|null $email */
            $email = $owner->getAttribute('email');
            /** @var int|string $key */
            $key = $owner->getKey();

            return $name ?? $displayName ?? $email ?? class_basename($owner).':'.(string) $key;
        }

        /** @var int|string $key */
        $key = $owner->getKey();

        return class_basename($owner).':'.(string) $key;
    }

    public function getRemainingUsesAttribute(): ?int
    {
        return $this->getRemainingUses();
    }

    /**
     * Get the human-readable value label (e.g., "10.50 %" or "RM 50.00").
     */
    public function getValueLabelAttribute(): string
    {
        $value = (int) $this->getAttribute('value');
        $type = $this->getAttribute('type');

        $enumType = $type instanceof VoucherType ? $type : VoucherType::tryFrom((string) $type);

        if ($enumType === VoucherType::Percentage) {
            // Value is stored in basis points (e.g., 1000 = 10.00%, 1259 = 12.59%)
            $percentage = $value / 100;

            return mb_rtrim(mb_rtrim(number_format($percentage, 2), '0'), '.').' %';
        }

        // Value is stored as cents
        $currency = mb_strtoupper((string) ($this->getAttribute('currency') ?? config('vouchers.default_currency', 'MYR')));

        return (string) Money::{$currency}($value);
    }

    /**
     * Get the total number of wallet entries for this voucher.
     */
    public function getWalletEntriesCountAttribute(): int
    {
        return $this->walletEntries()->count();
    }

    /**
     * Get the number of claimed wallet entries.
     */
    public function getWalletClaimedCountAttribute(): int
    {
        return $this->walletEntries()->where('is_claimed', true)->count();
    }

    /**
     * Get the number of redeemed wallet entries.
     */
    public function getWalletRedeemedCountAttribute(): int
    {
        return $this->walletEntries()->where('is_redeemed', true)->count();
    }

    /**
     * Get the number of available (not redeemed) wallet entries.
     */
    public function getWalletAvailableCountAttribute(): int
    {
        return $this->walletEntries()->where('is_redeemed', false)->count();
    }

    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'status' => VoucherStatus::class,
            'value' => 'integer', // Stored as cents or basis points
            'min_cart_value' => 'integer', // Stored as cents
            'max_discount' => 'integer', // Stored as cents
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'applied_count' => 'integer',
            'allows_manual_redemption' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
            'target_definition' => 'array',
        ];
    }
}
