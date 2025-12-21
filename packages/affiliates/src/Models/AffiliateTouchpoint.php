<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateTouchpoint extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_attribution_id',
        'affiliate_id',
        'affiliate_code',
        'source',
        'medium',
        'campaign',
        'term',
        'content',
        'metadata',
        'touched_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'touched_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.touchpoints', parent::getTable());
    }

    /**
     * @return BelongsTo<AffiliateAttribution, self>
     */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(AffiliateAttribution::class, 'affiliate_attribution_id');
    }

    /**
     * @return BelongsTo<Affiliate, self>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    protected static function booted(): void
    {
        // This model has no child relationships requiring cascade deletes
        // Kept for consistency with other models in the package
    }
}
