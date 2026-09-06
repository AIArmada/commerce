<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\PromotableProviderInterface;
use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\Services\ProgramCatalogService;
use AIArmada\Affiliates\Support\Catalog\PromotableRegistry;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->registry = new PromotableRegistry;
    $this->registry->register(new class implements PromotableProviderInterface
    {
        public function type(): string
        {
            return 'product';
        }

        public function list(?string $programId = null): iterable
        {
            return [
                ['subject_key' => 'SKU-1001', 'title' => 'Pro Plan', 'url' => 'https://site/x/sku-1001', 'amount_minor' => 9900, 'currency' => 'MYR', 'context' => ['product_id' => 'SKU-1001', 'category' => 'subs']],
                ['subject_key' => 'SKU-1002', 'title' => 'Basic', 'url' => 'https://site/x/sku-1002', 'amount_minor' => 4900, 'currency' => 'MYR', 'context' => ['product_id' => 'SKU-1002', 'category' => 'subs']],
            ];
        }
    });

    $this->service = new ProgramCatalogService(app(CommissionRuleEngine::class), $this->registry);

    $this->program = AffiliateProgram::create([
        'name' => 'Catalog Program',
        'slug' => 'catalog-program-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => CommissionType::Percentage,
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);
});

describe('ProgramCatalogService', function (): void {
    test('falls back to program default when no rules match', function (): void {
        $snapshot = $this->service->snapshot($this->program);

        expect($snapshot['program_id'])->toBe((string) $this->program->getKey());
        expect($snapshot['subjects'])->toHaveCount(2);
        expect($snapshot['subjects'][0]['effective']['rate_bp'])->toBe(1000);
        expect($snapshot['subjects'][0]['effective']['applied_rule_ids'])->toBe([]);
    });

    test('product rule wins over program default', function (): void {
        AffiliateCommissionRule::create([
            'program_id' => $this->program->getKey(),
            'name' => 'Pro plan boost',
            'rule_type' => CommissionRuleType::Product,
            'priority' => 90,
            'conditions' => ['product_id' => ['in' => ['SKU-1001']]],
            'commission_type' => CommissionType::Percentage,
            'commission_value' => 1500,
            'is_active' => true,
        ]);

        $snapshot = $this->service->snapshot($this->program);
        $byKey = collect($snapshot['subjects'])->keyBy('subject_key');

        expect($byKey['SKU-1001']['effective']['rate_bp'])->toBe(1500);
        expect($byKey['SKU-1001']['effective']['applied_rule_ids'])->toHaveCount(1);
        expect($byKey['SKU-1002']['effective']['rate_bp'])->toBe(1000);
    });

    test('volume and promotions are listed separately, never folded', function (): void {
        $usesBefore = AffiliateCommissionPromotion::query()->count();

        AffiliateVolumeTier::create([
            'program_id' => $this->program->getKey(),
            'name' => 'High volume',
            'min_volume_minor' => 100000,
            'commission_rate_basis_points' => 1800,
            'period' => 'monthly',
        ]);

        AffiliateCommissionPromotion::create([
            'program_id' => $this->program->getKey(),
            'name' => 'Spring',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => CarbonImmutable::now()->subDay(),
            'ends_at' => CarbonImmutable::now()->addDay(),
        ]);

        $snapshot = $this->service->snapshot($this->program);

        // base rate untouched by variable extras
        expect($snapshot['subjects'][0]['effective']['rate_bp'])->toBe(1000);
        expect($snapshot['variable_extras']['volume_tiers'])->toHaveCount(1);
        expect($snapshot['variable_extras']['promotions'])->toHaveCount(1);
        // snapshot must not burn promo usage
        expect(AffiliateCommissionPromotion::query()->count())->toBe($usesBefore + 1);
        expect(AffiliateCommissionPromotion::query()->sum('current_uses'))->toBe(0);
    });
});
