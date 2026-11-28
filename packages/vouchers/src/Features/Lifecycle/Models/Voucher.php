<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Models;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherDepletion;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherLookupInvalidation;
use AIArmada\Vouchers\Features\Lifecycle\States\Active;
use AIArmada\Vouchers\Features\Lifecycle\States\Depleted;
use AIArmada\Vouchers\Features\Lifecycle\States\Expired;
use AIArmada\Vouchers\Features\Lifecycle\States\Paused;
use AIArmada\Vouchers\Features\Lifecycle\States\VoucherStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property VoucherType $type
 * @property int $value Value in cents for fixed amounts, or basis points for percentage (e.g., 10.50% = 1050)
 * @property array<string, mixed>|null $value_config Configuration for compound voucher types (BOGO, Tiered, Bundle, Cashback)
 * @property string|null $credit_destination Destination for cashback credits (wallet, next_order, points)
 * @property int $credit_delay_hours Hours to wait before crediting cashback
 * @property string $currency
 * @property int|null $min_cart_value Value in cents
 * @property int|null $max_discount Value in cents
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_user
 * @property int $applied_count Canonical denormalized count of cart applications
 * @property bool $allows_manual_redemption
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $paused_at
 * @property CarbonImmutable|null $depleted_at
 * @property CarbonImmutable|null $last_activated_at
 * @property VoucherStatus $status
 * @property array<string, mixed>|null $target_definition
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $stacking_rules
 * @property array<string>|null $exclusion_groups
 * @property int $stacking_priority
 * @property string|null $promotion_id
 * @property string|null $affiliate_id
 * @property string|null $affiliate_program_id
 * @property CommissionType|null $affiliate_commission_type
 * @property int|null $affiliate_commission_value
 * @property list<array{level: int, type?: string, value?: int, share?: float}>|null $affiliate_upline_levels
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $times_used
 * @property-read float|null $usageProgress
 * @property-read string|null $owner_display_name
 * @property-read int|null $remaining_uses
 * @property-read string $value_label
 * @property-read Model|null $promotion
 */
class Voucher extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasStates;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'vouchers.owner';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'value_config',
        'credit_destination',
        'credit_delay_hours',
        'currency',
        'min_cart_value',
        'max_discount',
        'usage_limit',
        'usage_limit_per_user',
        'applied_count',
        'allows_manual_redemption',
        'starts_at',
        'expires_at',
        'paused_at',
        'depleted_at',
        'last_activated_at',
        'status',
        'metadata',
        'owner_type',
        'owner_id',
        'target_definition',
        'stacking_rules',
        'exclusion_groups',
        'stacking_priority',
        'promotion_id',
        'affiliate_id',
        'affiliate_program_id',
        'affiliate_commission_type',
        'affiliate_commission_value',
        'affiliate_upline_levels',
    ];

    public function getTable(): string
    {
        $tables = config('vouchers.database.tables', []);
        $prefix = config('vouchers.database.table_prefix', '');

        return $tables['vouchers'] ?? $prefix . 'vouchers';
    }

    /**
     * @return HasMany<VoucherUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    /**
     * Get the promotion that issued this voucher when the promotions package is installed.
     *
     * @return BelongsTo<Model, $this>
     */
    public function promotion(): BelongsTo
    {
        $promotionClass = '\\AIArmada\\Promotions\\Models\\Promotion';

        if (class_exists($promotionClass)) {
            /** @var class-string<Model> $promotionClass */

            return $this->belongsTo($promotionClass, 'promotion_id');
        }

        return $this->belongsTo(Model::class, 'promotion_id');
    }

    /**
     * Check if this voucher belongs to an affiliate.
     */
    public function belongsToAffiliate(): bool
    {
        return $this->affiliate_id !== null;
    }

    /**
     * Scope to filter vouchers by affiliate.
     *
     * @param  Builder<Voucher>  $query
     * @return Builder<Voucher>
     */
    public function scopeForAffiliate(Builder $query, string $affiliateId): Builder
    {
        return $query->where('affiliate_id', $affiliateId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, bool $includeGlobal = false): Builder
    {
        if (! config('vouchers.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = $includeGlobal && (bool) config('vouchers.owner.include_global', false);

        return $this->baseScopeForOwner($query, $owner, $includeGlobal);
    }

    public function allowsManualRedemption(): bool
    {
        return (bool) $this->getAttribute('allows_manual_redemption');
    }

    public function isActive(): bool
    {
        return $this->status instanceof Active;
    }

    /**
     * Scope vouchers that are active and inside their wall-clock window.
     *
     * @param  Builder<Voucher>  $query
     * @return Builder<Voucher>
     */
    public function scopeLive(Builder $query): Builder
    {
        $now = CarbonImmutable::now();
        $voucherTable = $this->getTable();

        // Aggregate usage counts once and join them in, instead of running a
        // correlated count subquery for every candidate row.
        $usageCounts = VoucherUsage::query()
            ->select('voucher_id')
            ->selectRaw('COUNT(*) as usage_count')
            ->groupBy('voucher_id');

        return $query
            ->where($voucherTable . '.status', VoucherStatus::normalize(Active::class))
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->leftJoinSub($usageCounts, 'voucher_usage_counts', static function (JoinClause $join) use ($voucherTable): void {
                $join->on('voucher_usage_counts.voucher_id', '=', $voucherTable . '.id');
            })
            ->where(function (Builder $builder) use ($voucherTable): void {
                $builder->whereNull($voucherTable . '.usage_limit')
                    ->orWhereRaw("COALESCE(voucher_usage_counts.usage_count, 0) < {$voucherTable}.usage_limit");
            });
    }

    public function isExpired(): bool
    {
        /** @var Carbon|null $expiresAt */
        $expiresAt = $this->getAttribute('expires_at');

        return $expiresAt !== null && $expiresAt->isPast();
    }

    /**
     * The status a reader should see. A past-due voucher reads as `Expired`
     * even when nothing has written the transition, because `Expired` is a
     * terminal state in the `VoucherStatus` graph and only this accessor keeps
     * display honest between sweeps. Mirrors the redemption path, which
     * `VoucherValidator` checks on `isExpired()` before it reads status.
     */
    public function getEffectiveStatusAttribute(): VoucherStatus
    {
        $status = $this->status;

        if ($this->isExpired() && ! $status instanceof Expired && ! $status instanceof Depleted) {
            return new Expired($this);
        }

        return $status;
    }

    public function hasStarted(): bool
    {
        /** @var Carbon|null $startsAt */
        $startsAt = $this->getAttribute('starts_at');

        return $startsAt === null || $startsAt <= CarbonImmutable::now();
    }

    public function hasUsageLimitRemaining(): bool
    {
        $usageLimit = $this->getAttribute('usage_limit');

        if ($usageLimit === null) {
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

    /**
     * Check if voucher should be marked as depleted and update status if so.
     *
     * Note: This does NOT increment usage - that's handled by VoucherUsage records.
     * Call this after recording usage to auto-update status when limit is reached.
     */
    public function checkIfDepleted(): void
    {
        app(VoucherDepletion::class)->handle((string) $this->getKey());
        $this->refresh();
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
            'abandoned_count' => max(0, $appliedCount - $usageCount),
            'conversion_rate' => $appliedCount === 0 ? null : (float) (($usageCount / $appliedCount) * 100),
            'remaining_uses' => $this->usage_limit === null ? null : max(0, $this->usage_limit - $usageCount),
        ];
    }

    /**
     * Redemption truth comes from voucher_usage rows. The usages_count value
     * is only a query optimization populated by QueriesVouchers.
     */
    public function getTimesUsedAttribute(): int
    {
        if (array_key_exists('usages_count', $this->attributes)) {
            $usagesCount = $this->attributes['usages_count'];

            return is_numeric($usagesCount) ? (int) $usagesCount : 0;
        }

        if ($this->relationLoaded('usages')) {
            return $this->usages->count();
        }

        return $this->usages()->count();
    }

    public function canBeRedeemed(): bool
    {
        return $this->isActive()
            && $this->hasStarted()
            && ! $this->isExpired()
            && $this->hasUsageLimitRemaining();
    }

    /**
     * Check if this voucher can stack with another voucher.
     *
     * Compares exclusion groups to determine if vouchers are mutually exclusive.
     */
    public function canStackWith(self $other): bool
    {
        $myGroups = $this->exclusion_groups ?? [];
        $otherGroups = $other->exclusion_groups ?? [];

        if (empty($myGroups) || empty($otherGroups)) {
            return true;
        }

        return empty(array_intersect($myGroups, $otherGroups));
    }

    /**
     * Get the stacking priority for ordering.
     */
    public function getStackingPriority(): int
    {
        return $this->stacking_priority ?? 100;
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
            $displayName = $owner->hasAttribute('display_name') ? $owner->getAttribute('display_name') : null;
            /** @var string|null $email */
            $email = $owner->getAttribute('email');
            /** @var int|string $key */
            $key = $owner->getKey();

            return $name ?? $displayName ?? $email ?? class_basename($owner) . ':' . (string) $key;
        }

        /** @var int|string $key */
        $key = $owner->getKey();

        return class_basename($owner) . ':' . (string) $key;
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

            return mb_rtrim(mb_rtrim(number_format($percentage, 2), '0'), '.') . ' %';
        }

        // Value is stored as cents
        $currency = (string) ($this->getAttribute('currency') ?? config('vouchers.default_currency', 'MYR'));

        return MoneyFormatter::formatMinor($value, $currency);
    }

    public function getPromotionSourceIdAttribute(): ?string
    {
        return $this->normalizePromotionSourceString($this->promotion_id);
    }

    public function getPromotionSourceNameAttribute(): ?string
    {
        if (! class_exists('\\AIArmada\\Promotions\\Models\\Promotion')) {
            return null;
        }

        return $this->normalizePromotionSourceString($this->promotion?->getAttribute('name'));
    }

    public function getPromotionSourceCodeAttribute(): ?string
    {
        if (! class_exists('\\AIArmada\\Promotions\\Models\\Promotion')) {
            return null;
        }

        return $this->normalizePromotionSourceString($this->promotion?->getAttribute('code'));
    }

    public function getPromotionSourceLabelAttribute(): ?string
    {
        $name = $this->promotion_source_name;
        $code = $this->promotion_source_code;

        if ($name !== null && $code !== null) {
            return $name . ' (' . $code . ')';
        }

        return $name ?? $code;
    }

    private function normalizePromotionSourceString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = mb_trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected static function booted(): void
    {
        self::saving(function (Voucher $voucher): void {
            if ($voucher->exists && $voucher->isDirty(['code', 'owner_type', 'owner_id'])) {
                $voucher->invalidateLookup(
                    (string) $voucher->getRawOriginal('code'),
                    $voucher->getRawOriginal('owner_type'),
                    $voucher->getRawOriginal('owner_id'),
                );
            }

            if ($voucher->isDirty('status')) {
                $originalStatus = VoucherStatus::fromString(
                    (string) $voucher->getRawOriginal('status'),
                    $voucher,
                );

                if ($voucher->status instanceof Paused && (! $originalStatus instanceof Paused)) {
                    $voucher->paused_at = CarbonImmutable::now();
                } elseif ($voucher->status instanceof Active && (! $originalStatus instanceof Active)) {
                    $voucher->last_activated_at = CarbonImmutable::now();
                    $voucher->paused_at = null;
                } elseif ($voucher->status instanceof Depleted && (! $originalStatus instanceof Depleted)) {
                    $voucher->depleted_at = CarbonImmutable::now();
                }
            }
        });

        self::saved(function (Voucher $voucher): void {
            $voucher->invalidateLookup($voucher->code, $voucher->owner_type, $voucher->owner_id);
        });

        self::creating(function (Voucher $voucher): void {
            if ($voucher->status instanceof Active) {
                $voucher->last_activated_at = CarbonImmutable::now();
            }
        });

        self::deleted(function (Voucher $voucher): void {
            $voucher->invalidateLookup($voucher->code, $voucher->owner_type, $voucher->owner_id);
        });
    }

    private function invalidateLookup(string $code, ?string $ownerType, string | int | null $ownerId): void
    {
        app(VoucherLookupInvalidation::class)->forgetIdentity(
            $code,
            $ownerType,
            $ownerId === null ? null : (string) $ownerId,
            $this->getConnection()->getName(),
        );
    }

    protected function casts(): array
    {
        $casts = [
            'type' => VoucherType::class,
            'status' => VoucherStatus::class,
            'value' => 'integer', // Stored as cents or basis points
            'value_config' => 'array',
            'credit_delay_hours' => 'integer',
            'min_cart_value' => 'integer', // Stored as cents
            'max_discount' => 'integer', // Stored as cents
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'applied_count' => 'integer',
            'allows_manual_redemption' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'depleted_at' => 'immutable_datetime',
            'last_activated_at' => 'immutable_datetime',
            'metadata' => 'array',
            'target_definition' => 'array',
            'stacking_rules' => 'array',
            'exclusion_groups' => 'array',
            'stacking_priority' => 'integer',
            'affiliate_commission_value' => 'integer',
            'affiliate_upline_levels' => 'array',
        ];

        if (class_exists(CommissionType::class)) {
            $casts['affiliate_commission_type'] = CommissionType::class;
        }

        return $casts;
    }

    /**
     * @return array<int, string>
     */
    public function getAuditInclude(): array
    {
        return [
            'code',
            'name',
            'type',
            'value',
            'value_config',
            'credit_destination',
            'credit_delay_hours',
            'currency',
            'min_cart_value',
            'max_discount',
            'usage_limit',
            'usage_limit_per_user',
            'applied_count',
            'allows_manual_redemption',
            'starts_at',
            'expires_at',
            'paused_at',
            'depleted_at',
            'last_activated_at',
            'status',
            'target_definition',
            'stacking_rules',
            'exclusion_groups',
            'stacking_priority',
            'promotion_id',
            'affiliate_id',
            'affiliate_program_id',
            'affiliate_commission_type',
            'affiliate_commission_value',
            'affiliate_upline_levels',
            'owner_type',
            'owner_id',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'code',
                'name',
                'type',
                'value',
                'currency',
                'usage_limit',
                'usage_limit_per_user',
                'applied_count',
                'allows_manual_redemption',
                'starts_at',
                'expires_at',
                'paused_at',
                'depleted_at',
                'last_activated_at',
                'status',
                'promotion_id',
                'affiliate_id',
                'affiliate_program_id',
                'affiliate_commission_type',
                'affiliate_commission_value',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('vouchers');
    }
}
