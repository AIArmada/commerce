<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Models\Concerns\ScopesByProgramOwner;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Volume-based tier for progressive commission rates.
 *
 * @property string $id
 * @property string|null $program_id
 * @property string $name
 * @property int $min_volume_minor
 * @property int|null $max_volume_minor
 * @property int $commission_rate_basis_points
 * @property string $period
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AffiliateProgram|null $program
 */
class AffiliateVolumeTier extends Model
{
    use HasUuids;
    use ScopesByProgramOwner;

    protected $fillable = [
        'program_id',
        'name',
        'min_volume_minor',
        'max_volume_minor',
        'commission_rate_basis_points',
        'period',
    ];

    protected $casts = [
        'min_volume_minor' => 'integer',
        'max_volume_minor' => 'integer',
        'commission_rate_basis_points' => 'integer',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.volume_tiers', 'affiliate_volume_tiers');
    }

    /**
     * @return BelongsTo<AffiliateProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    public function containsVolume(int $volumeMinor): bool
    {
        if ($volumeMinor < $this->min_volume_minor) {
            return false;
        }

        if ($this->max_volume_minor !== null && $volumeMinor > $this->max_volume_minor) {
            return false;
        }

        return true;
    }

    public function getCommissionRatePercentage(): float
    {
        return $this->commission_rate_basis_points / 100;
    }
}
