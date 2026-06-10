<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Models\Concerns\ScopesByAffiliateOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $reason
 * @property string|null $notes
 * @property Carbon|null $expires_at
 * @property string|null $placed_by
 * @property Carbon|null $released_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 */
class AffiliatePayoutHold extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasUuids;
    use LogsCommerceActivity;
    use ScopesByAffiliateOwner;

    protected $fillable = [
        'affiliate_id',
        'reason',
        'notes',
        'expires_at',
        'placed_by',
        'released_at',
    ];

    protected $casts = [
        'expires_at' => 'immutable_datetime',
        'released_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.payout_holds', 'affiliate_payout_holds');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function isActive(): bool
    {
        if ($this->released_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function release(): void
    {
        $this->update(['released_at' => now()]);
    }

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }
}
