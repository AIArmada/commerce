<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property int $level
 * @property int $min_personal_sales
 * @property int $min_team_sales
 * @property int $min_active_downlines
 * @property int $commission_rate_basis_points
 * @property array<string, int>|null $override_rates
 * @property array<string, mixed>|null $benefits
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property-read Collection<int, Affiliate> $affiliates
 * @property-read Model|null $owner
 */
class AffiliateRank extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $fillable = [
        'name',
        'slug',
        'level',
        'min_personal_sales',
        'min_team_sales',
        'min_active_downlines',
        'commission_rate_basis_points',
        'override_rates',
        'benefits',
        'metadata',
        'owner_type',
        'owner_id',
    ];

    protected $casts = [
        'level' => 'integer',
        'min_personal_sales' => 'integer',
        'min_team_sales' => 'integer',
        'min_active_downlines' => 'integer',
        'commission_rate_basis_points' => 'integer',
        'override_rates' => 'array',
        'benefits' => 'array',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.ranks', 'affiliate_ranks');
    }

    /**
     * @return HasMany<Affiliate, self>
     */
    public function affiliates(): HasMany
    {
        return $this->hasMany(Affiliate::class, 'rank_id');
    }

    public function isHigherThan(self $other): bool
    {
        return $this->level < $other->level;
    }

    public function isLowerThan(self $other): bool
    {
        return $this->level > $other->level;
    }

    public function getOverrideRateForDepth(int $depth): int
    {
        if (! is_array($this->override_rates)) {
            return 0;
        }

        return $this->override_rates[$depth] ?? 0;
    }

    /**
     * Check if an affiliate meets the qualification requirements for this rank.
     */
    public function meetsQualification(Affiliate $affiliate, int $personalSales, int $teamSales, int $activeDownlines): bool
    {
        if ($personalSales < $this->min_personal_sales) {
            return false;
        }

        if ($teamSales < $this->min_team_sales) {
            return false;
        }

        if ($activeDownlines < $this->min_active_downlines) {
            return false;
        }

        return true;
    }

    public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, bool $includeGlobal = false): Builder
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = $includeGlobal && (bool) config('affiliates.owner.include_global', false);

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    protected static function booted(): void
    {
        static::creating(function (self $rank): void {
            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($rank->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner) {
                $rank->owner_type = $owner->getMorphClass();
                $rank->owner_id = $owner->getKey();
            }
        });

        static::deleting(function (self $rank): void {
            $rank->affiliates()->update(['rank_id' => null]);
        });
    }
}
