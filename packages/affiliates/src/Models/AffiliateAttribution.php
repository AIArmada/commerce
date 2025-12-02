<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $affiliate_code
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
 * @property \Illuminate\Support\Carbon|null $first_seen_at
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $last_cookie_seen_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateConversion> $conversions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateTouchpoint> $touchpoints
 */
class AffiliateAttribution extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_id',
        'affiliate_code',
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
        return config('affiliates.table_names.attributions', parent::getTable());
    }

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

    public function refreshLastSeen(): void
    {
        $this->last_seen_at = now();

        if ($this->isDirty('last_seen_at')) {
            $this->save();
        }
    }

    protected static function booted(): void
    {
        static::deleting(function (self $attribution): void {
            $attribution->touchpoints()->delete();
            $attribution->conversions()->update(['affiliate_attribution_id' => null]);
        });
    }
}
