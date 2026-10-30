<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One immutable association per affiliate. Revoked rows are preserved as
 * history and continue to seal the affiliate: a revoked claim is never
 * re-claimable or reassignable.
 *
 * @property string $id
 * @property string $affiliate_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $claimant_type
 * @property string $claimant_id
 * @property string|null $capability_verifier_hash
 * @property string $status
 * @property CarbonInterface|null $claimed_at
 * @property CarbonInterface|null $revoked_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Affiliate $affiliate
 */
class AffiliateClaim extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $fillable = [
        'affiliate_id',
        'owner_type',
        'owner_id',
        'claimant_type',
        'claimant_id',
        'capability_verifier_hash',
        'status',
        'claimed_at',
        'revoked_at',
    ];

    protected $casts = [
        'claimed_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.claims', parent::getTable());
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function claimant(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'claimant_type', 'claimant_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->revoked_at === null;
    }

    public function belongsToClaimant(string $type, string | int $id): bool
    {
        return $this->claimant_type === $type && (string) $this->claimant_id === (string) $id;
    }
}
