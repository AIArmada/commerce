<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Models;

use AIArmada\Affiliates\Models\Concerns\ScopesByAffiliateOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
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
 */
class AffiliatePayoutHold extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasUuids;
    use LogsCommerceActivity;
    use ScopesByAffiliateOwner;

    protected $table = 'affiliate_payout_holds';

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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at')->where(function ($builder): void {
            $builder->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now());
        });
    }

    public function isActive(): bool
    {
        if ($this->released_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->lessThanOrEqualTo(CarbonImmutable::now())) {
            return false;
        }

        return true;
    }

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }
}
