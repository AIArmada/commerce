<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Models;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Closure table for efficient ancestor/descendant queries.
 *
 * @property string $id
 * @property string $ancestor_id
 * @property string $descendant_id
 * @property int $depth
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property-read Affiliate $ancestor
 * @property-read Affiliate $descendant
 */
class AffiliateUpline extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'ancestor_id',
        'descendant_id',
        'depth',
    ];

    protected $casts = [
        'depth' => 'integer',
    ];

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }

    public function getTable(): string
    {
        return config('affiliates.database.tables.upline', 'affiliate_upline');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'ancestor_id');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function descendant(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'descendant_id');
    }
}
