<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $verifier_hash
 * @property string $status
 * @property string|null $label
 * @property CarbonInterface|null $issued_at
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $consumed_at
 * @property CarbonInterface|null $revoked_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Affiliate $affiliate
 */
class AffiliateOwnershipCapability extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $fillable = [
        'affiliate_id',
        'owner_type',
        'owner_id',
        'verifier_hash',
        'status',
        'label',
        'issued_at',
        'expires_at',
        'consumed_at',
        'revoked_at',
    ];

    protected $casts = [
        'issued_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'consumed_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.ownership_capabilities', parent::getTable());
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function isUsableAt(CarbonInterface $moment): bool
    {
        return $this->status === 'active'
            && $this->revoked_at === null
            && $this->consumed_at === null
            && ($this->expires_at === null || $moment->lessThan($this->expires_at));
    }
}
