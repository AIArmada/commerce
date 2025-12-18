<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Time-limited promotional commission.
 *
 * @property string $id
 * @property string|null $program_id
 * @property string $name
 * @property string|null $description
 * @property string $bonus_type
 * @property int $bonus_value
 * @property array<string, mixed>|null $conditions
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int|null $max_uses
 * @property int $current_uses
 * @property array<string>|null $affiliate_ids
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AffiliateProgram|null $program
 */
class AffiliateCommissionPromotion extends Model
{
    use HasUuids;

    protected $fillable = [
        'program_id',
        'name',
        'description',
        'bonus_type',
        'bonus_value',
        'conditions',
        'starts_at',
        'ends_at',
        'max_uses',
        'current_uses',
        'affiliate_ids',
    ];

    protected $casts = [
        'bonus_value' => 'integer',
        'conditions' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_uses' => 'integer',
        'current_uses' => 'integer',
        'affiliate_ids' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.commission_promotions', 'affiliate_commission_promotions');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    public function isActive(): bool
    {
        if ($this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->current_uses >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function appliesToAffiliate(Affiliate $affiliate): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->affiliate_ids === null) {
            return true;
        }

        return in_array($affiliate->id, $this->affiliate_ids, true);
    }

    public function incrementUsage(): void
    {
        $this->increment('current_uses');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where(function ($q): void {
                $q->whereNull('max_uses')
                    ->orWhereColumn('current_uses', '<', 'max_uses');
            });
    }

    public function calculateBonus(int $baseCommissionMinor): int
    {
        return match ($this->bonus_type) {
            'percentage' => (int) round($baseCommissionMinor * $this->bonus_value / 10000),
            'flat' => $this->bonus_value,
            'multiplier' => (int) round($baseCommissionMinor * ($this->bonus_value / 100 - 1)),
            default => 0,
        };
    }
}
