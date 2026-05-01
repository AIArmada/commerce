<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pre-defined commission structures that can be applied to affiliates or programs.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property array<string, mixed> $rules
 * @property array<string, mixed>|null $metadata
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class AffiliateCommissionTemplate extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_default',
        'is_active',
        'rules',
        'metadata',
        'owner_type',
        'owner_id',
    ];

    /**
     * Get the default template.
     */
    public static function getDefault(): ?self
    {
        return self::query()->active()->default()->first();
    }

    /**
     * Create a standard percentage template.
     */
    public static function createStandardPercentage(
        string $name,
        int $rateBasisPoints = 1000,
        bool $isDefault = false
    ): self {
        $percentageRate = $rateBasisPoints / 100;

        return self::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => "Standard {$percentageRate}% commission on all sales",
            'is_default' => $isDefault,
            'is_active' => true,
            'rules' => [
                'commission_rules' => [
                    [
                        'type' => CommissionRuleType::Affiliate->value,
                        'commission_type' => CommissionType::Percentage->value,
                        'rate' => $rateBasisPoints,
                        'conditions' => [],
                    ],
                ],
                'volume_tiers' => [],
                'mlm_overrides' => [],
            ],
        ]);
    }

    /**
     * Create a tiered volume template.
     */
    public static function createTieredVolume(
        string $name,
        int $baseRateBasisPoints = 500,
        array $volumeTiers = []
    ): self {
        $defaultTiers = empty($volumeTiers) ? [
            ['min_volume' => 0, 'max_volume' => 100000, 'bonus_rate' => 0],
            ['min_volume' => 100001, 'max_volume' => 500000, 'bonus_rate' => 100],
            ['min_volume' => 500001, 'max_volume' => 1000000, 'bonus_rate' => 200],
            ['min_volume' => 1000001, 'max_volume' => null, 'bonus_rate' => 300],
        ] : $volumeTiers;

        return self::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'Volume-based tiered commission with bonuses',
            'is_default' => false,
            'is_active' => true,
            'rules' => [
                'commission_rules' => [
                    [
                        'type' => CommissionRuleType::Affiliate->value,
                        'commission_type' => CommissionType::Percentage->value,
                        'rate' => $baseRateBasisPoints,
                        'conditions' => [],
                    ],
                ],
                'volume_tiers' => $defaultTiers,
                'mlm_overrides' => [],
            ],
        ]);
    }

    /**
     * Create an MLM template with override commissions.
     */
    public static function createMlm(
        string $name,
        int $baseRateBasisPoints = 1000,
        array $overridePercentages = [50, 25, 10]
    ): self {
        $overrides = [];
        foreach ($overridePercentages as $level => $percentage) {
            $overrides[$level + 1] = $percentage;
        }

        return self::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'MLM structure with ' . count($overridePercentages) . '-level overrides',
            'is_default' => false,
            'is_active' => true,
            'rules' => [
                'commission_rules' => [
                    [
                        'type' => CommissionRuleType::Affiliate->value,
                        'commission_type' => CommissionType::Percentage->value,
                        'rate' => $baseRateBasisPoints,
                        'conditions' => [],
                    ],
                ],
                'volume_tiers' => [],
                'mlm_overrides' => $overrides,
            ],
        ]);
    }

    /**
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return Builder<self>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Get commission rules as structured array.
     *
     * @return array<int, array{
     *     type: string,
     *     commission_type: string,
     *     rate: int,
     *     conditions: array<string, mixed>
     * }>
     */
    public function getCommissionRules(): array
    {
        return $this->rules['commission_rules'] ?? [];
    }

    /**
     * Get volume tiers from template.
     *
     * @return array<int, array{
     *     min_volume: int,
     *     max_volume: int|null,
     *     bonus_rate: int
     * }>
     */
    public function getVolumeTiers(): array
    {
        return $this->rules['volume_tiers'] ?? [];
    }

    /**
     * Get MLM override percentages from template.
     *
     * @return array<int, int>
     */
    public function getMlmOverrides(): array
    {
        return $this->rules['mlm_overrides'] ?? [];
    }

    /**
     * Apply this template to an affiliate.
     */
    public function applyToAffiliate(Affiliate $affiliate): void
    {
        DB::transaction(function () use ($affiliate): void {
            $rules = $this->getCommissionRules();

            // Find the base commission rule
            $baseRule = collect($rules)->firstWhere('type', CommissionRuleType::Affiliate->value);

            if ($baseRule) {
                $affiliate->update([
                    'commission_type' => $baseRule['commission_type'],
                    'commission_rate' => $baseRule['rate'],
                ]);
            }
        });
    }

    /**
     * Apply this template to a program.
     */
    public function applyToProgram(AffiliateProgram $program): void
    {
        DB::transaction(function () use ($program): void {
            $rules = $this->getCommissionRules();

            // Find the base commission rule
            $baseRule = collect($rules)->firstWhere('type', CommissionRuleType::Program->value)
                ?? collect($rules)->first();

            if ($baseRule) {
                $program->update([
                    'commission_type' => $baseRule['commission_type'],
                    'default_commission_rate_basis_points' => $baseRule['rate'],
                ]);
            }
        });
    }

    public function getTable(): string
    {
        return config('affiliates.database.tables.commission_templates', 'affiliate_commission_templates');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, bool $includeGlobal = false): Builder
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = $includeGlobal && (bool) config('affiliates.owner.include_global', false);

        return $this->baseScopeForOwner($query, $owner, $includeGlobal);
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }

            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($template->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner) {
                $template->owner_type = $owner->getMorphClass();
                $template->owner_id = $owner->getKey();
            }
        });

        static::saving(function (self $template): void {
            // If this template is being set as default, unset other defaults within same owner
            if ($template->is_default && $template->isDirty('is_default')) {
                $query = static::query()
                    ->where('id', '!=', $template->id)
                    ->where('is_default', true);

                // Scope to same owner when owner mode is enabled
                if (config('affiliates.owner.enabled', false)) {
                    $query->where('owner_type', $template->owner_type)
                        ->where('owner_id', $template->owner_id);
                }

                $query->update(['is_default' => false]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'rules' => 'array',
            'metadata' => 'array',
        ];
    }
}
