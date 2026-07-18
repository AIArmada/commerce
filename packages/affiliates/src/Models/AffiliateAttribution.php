<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $affiliate_code
 * @property string|null $subject_type
 * @property string|null $subject_key
 * @property string|null $subject_id
 * @property string|null $subject_instance
 * @property string|null $subject_title_snapshot
 * @property string|null $cart_identifier
 * @property string $cart_instance
 * @property string|null $cookie_value
 * @property string|null $voucher_code
 * @property string|null $affiliate_program_id
 * @property array<string, mixed>|null $commission_override
 * @property list<array<string, mixed>>|null $upline_levels
 * @property string|null $source
 * @property string|null $medium
 * @property string|null $campaign
 * @property string|null $term
 * @property string|null $content
 * @property string|null $landing_url
 * @property string|null $referrer_url
 * @property string|null $user_agent
 * @property string|null $affiliate_link_id
 * @property string|null $attribution_type
 * @property string|null $visitor_key
 * @property string|null $channel
 * @property string|null $origin
 * @property string|null $sharer_user_id
 * @property string|null $ip_address
 * @property string|null $user_id
 * @property string|null $fingerprint
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $first_seen_at
 * @property CarbonInterface|null $last_seen_at
 * @property CarbonInterface|null $last_cookie_seen_at
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read AffiliateLink|null $affiliateLink
 * @property-read Collection<int, AffiliateConversion> $conversions
 * @property-read Collection<int, AffiliateTouchpoint> $touchpoints
 */
class AffiliateAttribution extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $fillable = [
        'affiliate_id',
        'affiliate_code',
        'subject_type',
        'subject_key',
        'subject_id',
        'subject_instance',
        'subject_title_snapshot',
        'cart_identifier',
        'cart_instance',
        'cookie_value',
        'voucher_code',
        'affiliate_program_id',
        'commission_override',
        'upline_levels',
        'source',
        'medium',
        'campaign',
        'term',
        'content',
        'landing_url',
        'referrer_url',
        'user_agent',
        'affiliate_link_id',
        'attribution_type',
        'visitor_key',
        'channel',
        'origin',
        'sharer_user_id',
        'ip_address',
        'user_id',
        'fingerprint',
        'metadata',
        'owner_type',
        'owner_id',
        'first_seen_at',
        'last_seen_at',
        'last_cookie_seen_at',
        'expires_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'commission_override' => 'array',
        'upline_levels' => 'array',
        'first_seen_at' => 'immutable_datetime',
        'last_seen_at' => 'immutable_datetime',
        'last_cookie_seen_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.attributions', parent::getTable());
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** @return BelongsTo<AffiliateLink, $this> */
    public function affiliateLink(): BelongsTo
    {
        return $this->belongsTo(AffiliateLink::class, 'affiliate_link_id');
    }

    /**
     * @return HasMany<AffiliateConversion, $this>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_attribution_id');
    }

    /**
     * @return HasMany<AffiliateTouchpoint, $this>
     */
    public function touchpoints(): HasMany
    {
        return $this->hasMany(AffiliateTouchpoint::class, 'affiliate_attribution_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
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

    public function refreshLastSeen(): void
    {
        $this->last_seen_at = now();

        if ($this->isDirty('last_seen_at')) {
            $this->save();
        }
    }

    protected static function booted(): void
    {
        static::creating(function (self $attribution): void {
            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($attribution->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner) {
                $attribution->owner_type = $owner->getMorphClass();
                $attribution->owner_id = $owner->getKey();
            }
        });

        static::deleting(function (self $attribution): void {
            $attribution->touchpoints()->delete();
            $attribution->conversions()->update(['affiliate_attribution_id' => null]);
        });
    }
}
