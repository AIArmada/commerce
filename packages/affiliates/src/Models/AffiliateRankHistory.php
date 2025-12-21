<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Models\Concerns\ScopesByAffiliateOwner;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string|null $from_rank_id
 * @property string|null $to_rank_id
 * @property RankQualificationReason $reason
 * @property Carbon $qualified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read AffiliateRank|null $fromRank
 * @property-read AffiliateRank|null $toRank
 */
class AffiliateRankHistory extends Model
{
    use HasUuids;
    use ScopesByAffiliateOwner;

    protected $fillable = [
        'affiliate_id',
        'from_rank_id',
        'to_rank_id',
        'reason',
        'qualified_at',
    ];

    protected $casts = [
        'reason' => RankQualificationReason::class,
        'qualified_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.rank_histories', 'affiliate_rank_histories');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function fromRank(): BelongsTo
    {
        return $this->belongsTo(AffiliateRank::class, 'from_rank_id');
    }

    public function toRank(): BelongsTo
    {
        return $this->belongsTo(AffiliateRank::class, 'to_rank_id');
    }

    public function isPromotion(): bool
    {
        if (! $this->fromRank || ! $this->toRank) {
            return $this->toRank !== null;
        }

        return $this->toRank->isHigherThan($this->fromRank);
    }

    public function isDemotion(): bool
    {
        if (! $this->fromRank || ! $this->toRank) {
            return $this->fromRank !== null && $this->toRank === null;
        }

        return $this->toRank->isLowerThan($this->fromRank);
    }
}
