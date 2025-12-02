<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $reference
 * @property string $status
 * @property int $total_minor
 * @property int $conversion_count
 * @property string $currency
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateConversion> $conversions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliatePayoutEvent> $events
 */
class AffiliatePayout extends Model
{
    use HasUuids;

    protected $fillable = [
        'reference',
        'status',
        'total_minor',
        'conversion_count',
        'currency',
        'metadata',
        'owner_type',
        'owner_id',
        'scheduled_at',
        'paid_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.payouts', parent::getTable());
    }

    /**
     * @return HasMany<AffiliateConversion, self>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_payout_id');
    }

    /**
     * @return HasMany<AffiliatePayoutEvent, self>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AffiliatePayoutEvent::class, 'affiliate_payout_id')->latest();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $payout): void {
            $payout->events()->delete();
            $payout->conversions()->update(['affiliate_payout_id' => null]);
        });
    }
}
