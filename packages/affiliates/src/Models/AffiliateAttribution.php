<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property string|null $subject_identifier
 * @property string|null $subject_instance
 * @property string|null $subject_title_snapshot
 * @property string|null $cart_identifier
 * @property string $cart_instance
 * @property string|null $cookie_value
 * @property string|null $voucher_code
 * @property string|null $source
 * @property string|null $medium
 * @property string|null $campaign
 * @property string|null $term
 * @property string|null $content
 * @property string|null $landing_url
 * @property string|null $referrer_url
 * @property string|null $user_agent
 * @property string|null $ip_address
 * @property string|null $user_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\CarbonInterface|null $first_seen_at
 * @property \Carbon\CarbonInterface|null $last_seen_at
 * @property \Carbon\CarbonInterface|null $last_cookie_seen_at
 * @property \Carbon\CarbonInterface|null $expires_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read Affiliate $affiliate
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
        'subject_identifier',
        'subject_instance',
        'subject_title_snapshot',
        'cart_identifier',
        'cart_instance',
        'cookie_value',
        'voucher_code',
        'source',
        'medium',
        'campaign',
        'term',
        'content',
        'landing_url',
        'referrer_url',
        'user_agent',
        'ip_address',
        'user_id',
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
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_cookie_seen_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.attributions', parent::getTable());
    }

    /**
     * Neutral alias for cart_identifier.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function subjectIdentifier(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['subject_identifier'] ?? $this->attributes['cart_identifier'] ?? null,
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * Neutral alias for cart_instance.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function subjectInstance(): Attribute
    {
        return Attribute::make(
            get: fn (): string => (string) ($this->attributes['subject_instance'] ?? $this->attributes['cart_instance'] ?? 'default'),
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * Compatibility alias for subject_identifier.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function cartIdentifier(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['cart_identifier'] ?? $this->attributes['subject_identifier'] ?? null,
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * Compatibility alias for subject_instance.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function cartInstance(): Attribute
    {
        return Attribute::make(
            get: fn (): string => (string) ($this->attributes['cart_instance'] ?? $this->attributes['subject_instance'] ?? 'default'),
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return HasMany<AffiliateConversion, self>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_attribution_id');
    }

    /**
     * @return HasMany<AffiliateTouchpoint, self>
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

            $owner = \AIArmada\CommerceSupport\Support\OwnerContext::resolve();

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
