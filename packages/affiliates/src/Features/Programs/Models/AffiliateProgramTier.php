<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Models;

use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\Concerns\ScopesByProgramOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $program_id
 * @property string $name
 * @property int $level
 * @property int $commission_rate_basis_points
 * @property int $min_conversions
 * @property int $min_revenue
 * @property string $min_revenue_currency
 * @property array<string, mixed>|null $benefits
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AffiliateProgram $program
 * @property-read Collection<int, AffiliateProgramMembership> $memberships
 */
class AffiliateProgramTier extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasUuids;
    use LogsCommerceActivity;
    use ScopesByProgramOwner;

    protected $fillable = [
        'program_id',
        'name',
        'level',
        'commission_rate_basis_points',
        'min_conversions',
        'min_revenue',
        'min_revenue_currency',
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
     * @return HasMany<AffiliateProgramMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(AffiliateProgramMembership::class, 'tier_id');
    }

    public function getCommissionRatePercentage(): float
    {
        return $this->commission_rate_basis_points / 100;
    }

    /**
     * Currency the min_revenue floor is denominated in.
     */
    public function revenueCurrency(): string
    {
        if (is_string($this->min_revenue_currency) && mb_trim($this->min_revenue_currency) !== '') {
            return mb_strtoupper(mb_trim($this->min_revenue_currency));
        }

        return mb_strtoupper((string) config('affiliates.currency.default', 'MYR'));
    }

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }
}
