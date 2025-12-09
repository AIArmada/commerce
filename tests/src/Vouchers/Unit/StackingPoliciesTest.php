<?php

declare(strict_types=1);

namespace Tests\Unit\Vouchers;

use AIArmada\Vouchers\Stacking\Enums\StackingMode;
use AIArmada\Vouchers\Stacking\Enums\StackingRuleType;
use AIArmada\Vouchers\Stacking\Rules\MaxVouchersRule;
use AIArmada\Vouchers\Stacking\Rules\MutualExclusionRule;
use AIArmada\Vouchers\Stacking\StackingDecision;
use AIArmada\Vouchers\Stacking\StackingPolicy;

describe('StackingMode Enum', function (): void {
    it('has all expected modes', function (): void {
        expect(StackingMode::None->value)->toBe('none');
        expect(StackingMode::Sequential->value)->toBe('sequential');
        expect(StackingMode::Parallel->value)->toBe('parallel');
        expect(StackingMode::BestDeal->value)->toBe('best_deal');
        expect(StackingMode::Custom->value)->toBe('custom');
    });

    it('returns human-readable labels', function (): void {
        expect(StackingMode::None->label())->toBe('Single Voucher Only');
        expect(StackingMode::Sequential->label())->toBe('Sequential Stacking');
        expect(StackingMode::Parallel->label())->toBe('Parallel Stacking');
    });

    it('indicates whether multiple vouchers are allowed', function (): void {
        expect(StackingMode::None->allowsMultipleVouchers())->toBeFalse();
        expect(StackingMode::Sequential->allowsMultipleVouchers())->toBeTrue();
        expect(StackingMode::Parallel->allowsMultipleVouchers())->toBeTrue();
        expect(StackingMode::BestDeal->allowsMultipleVouchers())->toBeTrue();
    });
});

describe('StackingRuleType Enum', function (): void {
    it('has all expected rule types', function (): void {
        expect(StackingRuleType::MaxVouchers->value)->toBe('max_vouchers');
        expect(StackingRuleType::MaxDiscount->value)->toBe('max_discount');
        expect(StackingRuleType::MaxDiscountPercentage->value)->toBe('max_discount_percentage');
        expect(StackingRuleType::MutualExclusion->value)->toBe('mutual_exclusion');
        expect(StackingRuleType::TypeRestriction->value)->toBe('type_restriction');
        expect(StackingRuleType::CategoryExclusion->value)->toBe('category_exclusion');
        expect(StackingRuleType::CampaignExclusion->value)->toBe('campaign_exclusion');
        expect(StackingRuleType::ValueThreshold->value)->toBe('value_threshold');
    });

    it('returns rule class names', function (): void {
        expect(StackingRuleType::MaxVouchers->getRuleClass())
            ->toBe(MaxVouchersRule::class);
        expect(StackingRuleType::MutualExclusion->getRuleClass())
            ->toBe(MutualExclusionRule::class);
    });
});

describe('StackingDecision', function (): void {
    it('can create an allowed decision', function (): void {
        $decision = StackingDecision::allow();

        expect($decision->allowed)->toBeTrue();
        expect($decision->isAllowed())->toBeTrue();
        expect($decision->isDenied())->toBeFalse();
        expect($decision->reason)->toBeNull();
        expect($decision->conflictsWith)->toBeNull();
    });

    it('can create a denied decision', function (): void {
        $decision = StackingDecision::deny('Maximum vouchers exceeded');

        expect($decision->allowed)->toBeFalse();
        expect($decision->isAllowed())->toBeFalse();
        expect($decision->isDenied())->toBeTrue();
        expect($decision->reason)->toBe('Maximum vouchers exceeded');
        expect($decision->getReason())->toBe('Maximum vouchers exceeded');
    });

    it('converts to array', function (): void {
        $decision = StackingDecision::deny('Test reason');
        $array = $decision->toArray();

        expect($array)->toBeArray();
        expect($array['allowed'])->toBeFalse();
        expect($array['reason'])->toBe('Test reason');
        expect($array['conflicts_with'])->toBeNull();
        expect($array['suggested_replacement'])->toBeNull();
    });
});

describe('StackingPolicy', function (): void {
    it('can create a default policy', function (): void {
        $policy = StackingPolicy::default();

        expect($policy->getMode())->toBe(StackingMode::Sequential);
        expect($policy->getRules())->toBeArray();
        expect($policy->getRules())->not->toBeEmpty();
        expect($policy->isAutoReplaceEnabled())->toBeTrue();
        expect($policy->isAutoOptimizeEnabled())->toBeFalse();
    });

    it('can create a single voucher policy', function (): void {
        $policy = StackingPolicy::singleVoucher();

        expect($policy->getMode())->toBe(StackingMode::None);
        expect($policy->isAutoReplaceEnabled())->toBeTrue();
    });

    it('can create an unlimited policy', function (): void {
        $policy = StackingPolicy::unlimited();

        expect($policy->getMode())->toBe(StackingMode::Sequential);
        expect($policy->getRules())->toBeEmpty();
        expect($policy->isAutoOptimizeEnabled())->toBeTrue();
        expect($policy->isAutoReplaceEnabled())->toBeFalse();
    });

    it('can be created from config', function (): void {
        $config = [
            'mode' => 'parallel',
            'rules' => [
                ['type' => 'max_vouchers', 'value' => 5],
            ],
            'auto_optimize' => true,
            'auto_replace' => false,
        ];

        $policy = StackingPolicy::fromConfig($config);

        expect($policy->getMode())->toBe(StackingMode::Parallel);
        expect($policy->getRules())->toHaveCount(1);
        expect($policy->isAutoOptimizeEnabled())->toBeTrue();
        expect($policy->isAutoReplaceEnabled())->toBeFalse();
    });

    it('can add rules fluently', function (): void {
        $policy = new StackingPolicy;
        $policy->addRule(['type' => 'max_vouchers', 'value' => 2]);

        expect($policy->getRules())->toHaveCount(1);
    });

    it('can change mode fluently', function (): void {
        $policy = new StackingPolicy;
        $policy->withMode(StackingMode::BestDeal);

        expect($policy->getMode())->toBe(StackingMode::BestDeal);
    });
});
