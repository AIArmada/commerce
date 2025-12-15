<?php

declare(strict_types=1);

use AIArmada\Vouchers\Stacking\Enums\StackingMode;
use AIArmada\Vouchers\Stacking\Enums\StackingRuleType;
use AIArmada\Vouchers\Stacking\Rules\CampaignExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\CategoryExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\MaxDiscountPercentageRule;
use AIArmada\Vouchers\Stacking\Rules\MaxDiscountRule;
use AIArmada\Vouchers\Stacking\Rules\MaxVouchersRule;
use AIArmada\Vouchers\Stacking\Rules\MutualExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\TypeRestrictionRule;
use AIArmada\Vouchers\Stacking\Rules\ValueThresholdRule;

describe('StackingMode Enum', function (): void {
    it('has correct values', function (): void {
        expect(StackingMode::None->value)->toBe('none')
            ->and(StackingMode::Sequential->value)->toBe('sequential')
            ->and(StackingMode::Parallel->value)->toBe('parallel')
            ->and(StackingMode::BestDeal->value)->toBe('best_deal')
            ->and(StackingMode::Custom->value)->toBe('custom');
    });

    it('returns correct labels', function (): void {
        expect(StackingMode::None->label())->toBe('Single Voucher Only')
            ->and(StackingMode::Sequential->label())->toBe('Sequential Stacking')
            ->and(StackingMode::Parallel->label())->toBe('Parallel Stacking')
            ->and(StackingMode::BestDeal->label())->toBe('Best Deal Auto-Select')
            ->and(StackingMode::Custom->label())->toBe('Custom Policy');
    });

    it('returns correct descriptions', function (): void {
        expect(StackingMode::None->description())->toContain('Only one voucher')
            ->and(StackingMode::Sequential->description())->toContain('one after another')
            ->and(StackingMode::Parallel->description())->toContain('original cart total')
            ->and(StackingMode::BestDeal->description())->toContain('automatically selects')
            ->and(StackingMode::Custom->description())->toContain('custom policy');
    });

    it('correctly identifies if multiple vouchers are allowed', function (): void {
        expect(StackingMode::None->allowsMultipleVouchers())->toBeFalse()
            ->and(StackingMode::Sequential->allowsMultipleVouchers())->toBeTrue()
            ->and(StackingMode::Parallel->allowsMultipleVouchers())->toBeTrue()
            ->and(StackingMode::BestDeal->allowsMultipleVouchers())->toBeTrue()
            ->and(StackingMode::Custom->allowsMultipleVouchers())->toBeTrue();
    });

    it('has all expected cases', function (): void {
        $cases = StackingMode::cases();

        expect($cases)->toHaveCount(5)
            ->and($cases)->toContain(StackingMode::None)
            ->and($cases)->toContain(StackingMode::Sequential)
            ->and($cases)->toContain(StackingMode::Parallel)
            ->and($cases)->toContain(StackingMode::BestDeal)
            ->and($cases)->toContain(StackingMode::Custom);
    });
});

describe('StackingRuleType Enum', function (): void {
    it('has correct values', function (): void {
        expect(StackingRuleType::MaxVouchers->value)->toBe('max_vouchers')
            ->and(StackingRuleType::MaxDiscount->value)->toBe('max_discount')
            ->and(StackingRuleType::MaxDiscountPercentage->value)->toBe('max_discount_percentage')
            ->and(StackingRuleType::MutualExclusion->value)->toBe('mutual_exclusion')
            ->and(StackingRuleType::TypeRestriction->value)->toBe('type_restriction')
            ->and(StackingRuleType::CategoryExclusion->value)->toBe('category_exclusion')
            ->and(StackingRuleType::CampaignExclusion->value)->toBe('campaign_exclusion')
            ->and(StackingRuleType::ValueThreshold->value)->toBe('value_threshold');
    });

    it('returns correct labels', function (): void {
        expect(StackingRuleType::MaxVouchers->label())->toBe('Maximum Vouchers')
            ->and(StackingRuleType::MaxDiscount->label())->toBe('Maximum Discount Amount')
            ->and(StackingRuleType::MaxDiscountPercentage->label())->toBe('Maximum Discount Percentage')
            ->and(StackingRuleType::MutualExclusion->label())->toBe('Mutual Exclusion Groups')
            ->and(StackingRuleType::TypeRestriction->label())->toBe('Voucher Type Restriction')
            ->and(StackingRuleType::CategoryExclusion->label())->toBe('Category Exclusion')
            ->and(StackingRuleType::CampaignExclusion->label())->toBe('Campaign Exclusion')
            ->and(StackingRuleType::ValueThreshold->label())->toBe('Minimum Cart Value');
    });

    it('returns correct descriptions', function (): void {
        expect(StackingRuleType::MaxVouchers->description())->toContain('how many vouchers')
            ->and(StackingRuleType::MaxDiscount->description())->toContain('total discount amount')
            ->and(StackingRuleType::MaxDiscountPercentage->description())->toContain('percentage')
            ->and(StackingRuleType::MutualExclusion->description())->toContain('exclusion group')
            ->and(StackingRuleType::TypeRestriction->description())->toContain('each type')
            ->and(StackingRuleType::CategoryExclusion->description())->toContain('same category')
            ->and(StackingRuleType::CampaignExclusion->description())->toContain('same campaign')
            ->and(StackingRuleType::ValueThreshold->description())->toContain('minimum cart value');
    });

    it('returns correct rule classes', function (): void {
        expect(StackingRuleType::MaxVouchers->getRuleClass())->toBe(MaxVouchersRule::class)
            ->and(StackingRuleType::MaxDiscount->getRuleClass())->toBe(MaxDiscountRule::class)
            ->and(StackingRuleType::MaxDiscountPercentage->getRuleClass())->toBe(MaxDiscountPercentageRule::class)
            ->and(StackingRuleType::MutualExclusion->getRuleClass())->toBe(MutualExclusionRule::class)
            ->and(StackingRuleType::TypeRestriction->getRuleClass())->toBe(TypeRestrictionRule::class)
            ->and(StackingRuleType::CategoryExclusion->getRuleClass())->toBe(CategoryExclusionRule::class)
            ->and(StackingRuleType::CampaignExclusion->getRuleClass())->toBe(CampaignExclusionRule::class)
            ->and(StackingRuleType::ValueThreshold->getRuleClass())->toBe(ValueThresholdRule::class);
    });

    it('has all expected cases', function (): void {
        $cases = StackingRuleType::cases();

        expect($cases)->toHaveCount(8);
    });

    it('can instantiate all rule classes', function (): void {
        foreach (StackingRuleType::cases() as $ruleType) {
            $ruleClass = $ruleType->getRuleClass();
            $instance = new $ruleClass;

            expect($instance)->toBeObject()
                ->and($instance->getType())->toBe($ruleType->value);
        }
    });
});
