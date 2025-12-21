<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Models\Concerns\ScopesByAffiliateOwner;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
class AffiliatePayoutHold extends Model
{
    use HasUuids;
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
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.payout_holds', 'affiliate_payout_holds');
    }

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
}
