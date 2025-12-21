<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $cart_identifier
 * @property string $cart_instance
 * @property array<string, mixed>|null $metadata
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

    /**
     * @return BelongsTo<Affiliate, self>
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
