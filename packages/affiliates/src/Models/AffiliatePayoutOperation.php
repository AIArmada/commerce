<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string|null $affiliate_payout_id
 * @property string $operation_key
 * @property string $status
 * @property int $amount_minor
 * @property string $currency
 * @property int|null $payout_sequence
 * @property string|null $provider_reference
 * @property string|null $last_error_code
 * @property Carbon $claimed_at
 * @property Carbon|null $lease_expires_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $funds_released_at
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Affiliate|null $affiliate
 * @property-read AffiliatePayout|null $payout
 */
final class AffiliatePayoutOperation extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $fillable = [
        'affiliate_id',
        'affiliate_payout_id',
        'operation_key',
        'status',
        'amount_minor',
        'currency',
        'payout_sequence',
        'provider_reference',
        'last_error_code',
        'claimed_at',
        'lease_expires_at',
        'completed_at',
        'funds_released_at',
        'owner_type',
        'owner_id',
    ];

    protected $hidden = ['provider_reference'];

    public function getTable(): string
    {
        return config('affiliates.database.tables.payout_operations', 'affiliate_payout_operations');
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** @return BelongsTo<AffiliatePayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'payout_sequence' => 'integer',
            'claimed_at' => 'immutable_datetime',
            'lease_expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'funds_released_at' => 'immutable_datetime',
        ];
    }
}
