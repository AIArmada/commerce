<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property AffiliateStatus $status
 * @property CommissionType $commission_type
 * @property int $commission_rate
 * @property string $currency
 * @property string|null $parent_affiliate_id
 * @property string|null $rank_id
 * @property int $network_depth
 * @property int $direct_downline_count
 * @property int $total_downline_count
 * @property string|null $default_voucher_code
 * @property string|null $contact_email
 * @property string|null $website_url
 * @property string|null $payout_terms
 * @property string|null $tracking_domain
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Affiliate|null $parent
 * @property-read AffiliateRank|null $rank
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Affiliate> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateAttribution> $attributions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateConversion> $conversions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateFraudSignal> $fraudSignals
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateDailyStat> $dailyStats
 * @property-read AffiliateBalance|null $balance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliatePayoutMethod> $payoutMethods
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliatePayoutHold> $payoutHolds
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliatePayout> $payouts
 * @property-read Model|null $owner
 */
class Affiliate extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'commission_type',
        'commission_rate',
        'currency',
        'parent_affiliate_id',
        'rank_id',
        'network_depth',
        'direct_downline_count',
        'total_downline_count',
        'default_voucher_code',
        'contact_email',
        'website_url',
        'payout_terms',
        'tracking_domain',
        'metadata',
        'owner_type',
        'owner_id',
        'activated_at',
    ];

    protected $casts = [
        'status' => AffiliateStatus::class,
        'commission_type' => CommissionType::class,
        'network_depth' => 'integer',
        'direct_downline_count' => 'integer',
        'total_downline_count' => 'integer',
        'metadata' => 'array',
        'activated_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.affiliates', parent::getTable());
    }

    /**
     * @return HasMany<AffiliateAttribution, self>
     */
    public function attributions(): HasMany
    {
        return $this->hasMany(AffiliateAttribution::class);
    }

    /**
     * @return HasMany<AffiliateConversion, self>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class);
    }

    /**
     * @return HasMany<AffiliatePayout, self>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_affiliate_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_affiliate_id');
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(AffiliateRank::class, 'rank_id');
    }

    /**
     * @return HasMany<AffiliateFraudSignal, self>
     */
    public function fraudSignals(): HasMany
    {
        return $this->hasMany(AffiliateFraudSignal::class);
    }

    /**
     * @return HasMany<AffiliateDailyStat, self>
     */
    public function dailyStats(): HasMany
    {
        return $this->hasMany(AffiliateDailyStat::class);
    }

    public function balance(): HasOne
    {
        return $this->hasOne(AffiliateBalance::class);
    }

    /**
     * @return HasMany<AffiliatePayoutMethod, self>
     */
    public function payoutMethods(): HasMany
    {
        return $this->hasMany(AffiliatePayoutMethod::class);
    }

    /**
     * @return HasMany<AffiliatePayoutHold, self>
     */
    public function payoutHolds(): HasMany
    {
        return $this->hasMany(AffiliatePayoutHold::class);
    }

    /**
     * Get the owner model (polymorphic relationship).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasActivePayoutHold(): bool
    {
        return $this->payoutHolds()
            ->whereNull('released_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function canRequestPayout(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->hasActivePayoutHold()) {
            return false;
        }

        $balance = $this->balance;
        if (! $balance) {
            return false;
        }

        return $balance->canRequestPayout();
    }

    public function isActive(): bool
    {
        return $this->status === AffiliateStatus::Active;
    }

    public function scopeForOwner(Builder $query, ?Model $owner = null): Builder
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $owner ??= app(OwnerResolverInterface::class)->resolve();

        if (! $owner) {
            return $query;
        }

        return $query->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }

    protected static function booted(): void
    {
        static::creating(function (self $affiliate): void {
            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($affiliate->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = app(OwnerResolverInterface::class)->resolve();

            if ($owner) {
                $affiliate->owner_type = $owner->getMorphClass();
                $affiliate->owner_id = $owner->getKey();
            }
        });

        static::deleting(function (self $affiliate): void {
            $affiliate->attributions()->delete();
            $affiliate->conversions()->delete();
            $affiliate->children()->update(['parent_affiliate_id' => null]);
        });
    }
}
