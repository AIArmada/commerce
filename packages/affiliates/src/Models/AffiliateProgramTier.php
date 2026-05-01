<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Models\Concerns\ScopesByProgramOwner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string $program_id
 * @property string $name
 * @property int $level
 * @property int $commission_rate_basis_points
 * @property int $min_conversions
 * @property int $min_revenue
 * @property array<string, mixed>|null $benefits
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AffiliateProgram $program
 * @property-read Collection<int, AffiliateProgramMembership> $memberships
 */
class AffiliateProgramTier extends Model
{
    use HasUuids;
    use ScopesByProgramOwner;

    protected $fillable = [
        'program_id',
        'name',
        'level',
        'commission_rate_basis_points',
        'min_conversions',
        'min_revenue',
        'benefits',
    ];

    protected $casts = [
        'level' => 'integer',
        'commission_rate_basis_points' => 'integer',
        'min_conversions' => 'integer',
        'min_revenue' => 'integer',
        'benefits' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.program_tiers', 'affiliate_program_tiers');
    }

    /**
     * @return BelongsTo<AffiliateProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    /**
     * @return HasMany<AffiliateProgramMembership, self>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(AffiliateProgramMembership::class, 'tier_id');
    }

    public function meetsUpgradeRequirements(Affiliate $affiliate, AffiliateProgram $program): bool
    {
        $conversions = $this->programConversions($affiliate, $program)
            ->count();

        if ($conversions < $this->min_conversions) {
            return false;
        }

        $revenue = (int) $this->programConversions($affiliate, $program)
            ->sum(DB::raw('COALESCE(NULLIF(value_minor, 0), total_minor, 0)'));

        if ($revenue < $this->min_revenue) {
            return false;
        }

        return true;
    }

    public function getCommissionRatePercentage(): float
    {
        return $this->commission_rate_basis_points / 100;
    }

    /**
     * @return HasMany<AffiliateConversion, Affiliate>
     */
    private function programConversions(Affiliate $affiliate, AffiliateProgram $program): HasMany
    {
        return $affiliate->conversions()
            ->where(function ($query) use ($program): void {
                $query->where('metadata->program_id', $program->id)
                    ->orWhereHas('attribution', function ($attributionQuery) use ($program): void {
                        $attributionQuery->where('metadata->program_id', $program->id);
                    });
            });
    }

    protected static function booted(): void
    {
        static::deleting(function (self $tier): void {
            // Set tier_id to null on memberships when tier is deleted
            $tier->memberships()->update(['tier_id' => null]);
        });
    }
}
