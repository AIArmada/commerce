<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Models;

use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\FormatsMoney;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Pricing\Support\PricingOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a price list (e.g., Retail, Wholesale, VIP).
 *
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $currency
 * @property int $priority
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class PriceList extends Model
{
    use FormatsMoney;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'pricing.features.owner';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'currency',
        'priority',
        'is_default',
        'is_active',
        'customer_id',
        'segment_id',
        'starts_at',
        'ends_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'priority' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 0,
        'is_default' => false,
        'is_active' => true,
    ];

    public function getTable(): string
    {
        return config('pricing.database.tables.price_lists', 'price_lists');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * @return HasMany<Price, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    /**
     * Price tiers associated with this price list.
     *
     * @return HasMany<PriceTier, $this>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(PriceTier::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @param  EloquentModel|null  $owner  The owner to scope to
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner = null, bool $includeGlobal = true): Builder
    {
        if (! PricingOwnerScope::isEnabled()) {
            return $query;
        }

        $includeGlobal = $includeGlobal && PricingOwnerScope::includeGlobal();

        $ownerToScope = $owner;

        if (func_num_args() < 2) {
            $ownerToScope = OwnerContext::CURRENT;
        }

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobal);

        return $scoped;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        if ($this->ends_at && $this->ends_at < $now) {
            return false;
        }

        return true;
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'priority', 'is_active', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->useLogName('pricing');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::updating(function (PriceList $priceList): void {
            if (! PricingOwnerScope::isEnabled()) {
                return;
            }

            $owner = PricingOwnerScope::resolveOwner();

            if ($owner !== null && ! $priceList->belongsToOwner($owner)) {
                throw new AuthorizationException('Cannot update price lists outside the current owner scope.');
            }
        });

        static::saving(function (PriceList $priceList): void {
            if (! PricingOwnerScope::isEnabled()) {
                return;
            }

            $hasOwnerType = $priceList->owner_type !== null;
            $hasOwnerId = $priceList->owner_id !== null;

            if ($hasOwnerType !== $hasOwnerId) {
                throw new InvalidArgumentException('Invalid owner columns: owner_type and owner_id must be both set or both null.');
            }

            $owner = PricingOwnerScope::resolveOwner();

            if ($owner === null) {
                if ($priceList->owner_type !== null || $priceList->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned price lists without an owner context.');
                }

                return;
            }

            if (
                ! $priceList->exists
                && $priceList->owner_type === null
                && $priceList->owner_id === null
                && (bool) config('pricing.features.owner.auto_assign_on_create', true)
            ) {
                $priceList->assignOwner($owner);
            }

            if (
                ($priceList->owner_type !== null || $priceList->owner_id !== null)
                && ! $priceList->belongsToOwner($owner)
            ) {
                throw new AuthorizationException('Cannot write price lists outside the current owner scope.');
            }
        });

        static::deleting(function (PriceList $priceList): void {
            if (PricingOwnerScope::isEnabled()) {
                $owner = PricingOwnerScope::resolveOwner();

                if ($owner === null) {
                    if ($priceList->owner_type !== null || $priceList->owner_id !== null) {
                        throw new AuthorizationException('Cannot delete owned price lists without an owner context.');
                    }
                } elseif (! $priceList->belongsToOwner($owner)) {
                    throw new AuthorizationException('Cannot delete price lists outside the current owner scope.');
                }
            }

            $priceList->prices()->delete();
            $priceList->tiers()->delete();
        });
    }
}
