<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $affiliate_attribution_id
 * @property string $affiliate_id
 * @property string $affiliate_code
 * @property string|null $source
 * @property string|null $medium
 * @property string|null $campaign
 * @property string|null $term
 * @property string|null $content
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\CarbonInterface|null $touched_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read AffiliateAttribution $attribution
 * @property-read Affiliate $affiliate
 */
class AffiliateTouchpoint extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $fillable = [
        'affiliate_attribution_id',
        'affiliate_id',
        'affiliate_code',
        'source',
        'medium',
        'campaign',
        'term',
        'content',
        'owner_type',
        'owner_id',
        'ip_address',
        'user_agent',
        'metadata',
        'touched_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'touched_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.touchpoints', parent::getTable());
    }

    /**
     * @return BelongsTo<AffiliateAttribution, $this>
     */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(AffiliateAttribution::class, 'affiliate_attribution_id');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
