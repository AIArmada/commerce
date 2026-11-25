<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Actions;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionTemplateAdministration;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAdministration;
use AIArmada\Affiliates\Features\Programs\Api\ProgramAdministration;
use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final readonly class AdministerCommissionTemplates implements CommissionTemplateAdministration
{
    public function __construct(
        private AffiliateAdministration $affiliates,
        private ProgramAdministration $programs,
    ) {}

    public function create(array $attributes): string
    {
        return (new AffiliateCommissionTemplate)->getConnection()->transaction(function () use ($attributes): string {
            $template = new AffiliateCommissionTemplate($this->attributes($attributes));
            if (config('affiliates.owner.enabled', false)) {
                $owner = OwnerContext::resolveConfigured(AffiliateCommissionTemplate::ownerScopeConfig());
                OwnerContext::assertResolvedOrExplicitGlobal($owner, 'Template creation requires an owner context or explicit global context.');
                if ($owner !== null) {
                    $template->owner()->associate($owner);
                }
            }

            $this->persist($template);

            return (string) $template->getKey();
        }, attempts: 3);
    }

    public function update(string $templateId, array $attributes): void
    {
        (new AffiliateCommissionTemplate)->getConnection()->transaction(function () use ($templateId, $attributes): void {
            $template = $this->lockTemplate($templateId);
            $template->fill($this->attributes($attributes));
            $this->persist($template);
        }, attempts: 3);
    }

    /**
     * Create a standard percentage template.
     */
    public function createStandardPercentage(
        string $name,
        int $rateBasisPoints = 1000,
        bool $isDefault = false
    ): string {
        $percentageRate = $rateBasisPoints / 100;

        return $this->create([
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
    public function createTieredVolume(
        string $name,
        int $baseRateBasisPoints = 500,
        array $volumeTiers = []
    ): string {
        $defaultTiers = empty($volumeTiers) ? [
            ['min_volume' => 0, 'max_volume' => 100000, 'bonus_rate' => 0],
            ['min_volume' => 100001, 'max_volume' => 500000, 'bonus_rate' => 100],
            ['min_volume' => 500001, 'max_volume' => 1000000, 'bonus_rate' => 200],
            ['min_volume' => 1000001, 'max_volume' => null, 'bonus_rate' => 300],
        ] : $volumeTiers;

        return $this->create([
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
    public function createMlm(
        string $name,
        int $baseRateBasisPoints = 1000,
        array $overridePercentages = [50, 25, 10]
    ): string {
        $overrides = [];
        foreach ($overridePercentages as $level => $percentage) {
            $overrides[$level + 1] = $percentage;
        }

        return $this->create([
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

    public function applyToAffiliate(string $templateId, string $affiliateId): void
    {
        $template = AffiliateCommissionTemplate::query()->active()->findOrFail($templateId);
        $rule = collect($template->getCommissionRules())->firstWhere('type', CommissionRuleType::Affiliate->value);
        if ($rule !== null) {
            $this->affiliates->update($affiliateId, [
                'commission_type' => $rule['commission_type'],
                'commission_rate' => $rule['rate'],
            ]);
        }
    }

    public function applyToProgram(string $templateId, string $programId): void
    {
        $template = AffiliateCommissionTemplate::query()->active()->findOrFail($templateId);
        $rules = collect($template->getCommissionRules());
        $rule = $rules->firstWhere('type', CommissionRuleType::Program->value) ?? $rules->first();
        if ($rule !== null) {
            $this->programs->update($programId, [
                'commission_type' => $rule['commission_type'],
                'default_commission_rate_basis_points' => $rule['rate'],
            ]);
        }
    }

    private function lockTemplate(string $templateId): AffiliateCommissionTemplate
    {
        if (config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliateCommissionTemplate::class, $templateId);
        }

        return AffiliateCommissionTemplate::query()->lockForUpdate()->findOrFail($templateId);
    }

    private function persist(AffiliateCommissionTemplate $template): void
    {
        if ($template->is_default) {
            $query = AffiliateCommissionTemplate::query()->where('is_default', true);
            if ($template->exists) {
                $query->whereKeyNot($template->getKey());
            }
            if (config('affiliates.owner.enabled', false)) {
                $query->where('owner_type', $template->owner_type)->where('owner_id', $template->owner_id);
            }
            $query->update(['is_default' => false, 'default_owner_key' => null]);
        }

        $template->forceFill([
            'default_owner_key' => $template->is_default
                ? hash('sha256', json_encode([$template->owner_type, $template->owner_id], JSON_THROW_ON_ERROR))
                : null,
        ]);
        $template->save();
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function attributes(array $attributes): array
    {
        return Arr::only($attributes, ['name', 'slug', 'description', 'is_default', 'is_active', 'rules', 'metadata']);
    }
}
