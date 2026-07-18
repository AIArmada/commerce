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
 * @property string $affiliate_attribution_id
 * @property string $affiliate_id
 * @property string $affiliate_code
 * @property string|null $subject_type
 * @property string|null $subject_key
 * @property string|null $subject_id
 * @property string|null $subject_instance
 * @property string|null $subject_title_snapshot
 * @property string|null $source
 * @property string|null $medium
 * @property string|null $campaign
 * @property string|null $term
 * @property string|null $content
 * @property string|null $affiliate_link_id
 * @property string|null $touchpoint_type
 * @property string|null $interaction_type
 * @property string|null $visitor_key
 * @property string|null $channel
 * @property string|null $origin
 * @property string|null $url
 * @property string|null $referrer_url
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $fingerprint
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $touched_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
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
        'subject_type',
        'subject_key',
        'subject_id',
        'subject_instance',
        'subject_title_snapshot',
        'source',
        'medium',
        'campaign',
        'term',
        'content',
        'affiliate_link_id',
        'touchpoint_type',
        'interaction_type',
        'visitor_key',
        'channel',
        'origin',
        'url',
        'referrer_url',
        'owner_type',
        'owner_id',
        'ip_address',
        'user_agent',
        'fingerprint',
        'metadata',
        'touched_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'touched_at' => 'immutable_datetime',
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
