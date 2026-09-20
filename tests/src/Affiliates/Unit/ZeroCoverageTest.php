<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\MatureConversion;
use AIArmada\Affiliates\Actions\Conversions\ProcessConversionMaturity;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Services\Commissions\CommissionCalculationResult;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\QualifiedConversion;

// MatureConversion Action Tests
test('MatureConversion returns false for non-qualified conversion', function (): void {
    $action = app(MatureConversion::class);

    $affiliate = Affiliate::create([
        'code' => 'MATURE001',
        'name' => 'Mature Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-MATURE-001',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now()->subDays(60),
    ]);

    $result = $action->handle($conversion);

    expect($result)->toBeFalse();
});

test('MatureConversion returns false for conversion with future maturity date', function (): void {
    $action = app(MatureConversion::class);

    $affiliate = Affiliate::create([
        'code' => 'MATURE002',
        'name' => 'Future Mature Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-MATURE-002',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => QualifiedConversion::class,
        'occurred_at' => now(), // Just occurred, not mature yet
    ]);

    $result = $action->handle($conversion);

    expect($result)->toBeFalse();
});

// ProcessConversionMaturity Action Tests
test('ProcessConversionMaturity matures due conversions and skips the rest', function (): void {
    $action = app(ProcessConversionMaturity::class);

    $affiliate = Affiliate::create([
        'code' => 'MATURE003',
        'name' => 'Maturity Batch Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $due = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-MATURE-003',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => QualifiedConversion::class,
        'occurred_at' => now()->subDays(60),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-MATURE-004',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => QualifiedConversion::class,
        'occurred_at' => now(),
    ]);

    expect($action->handle())->toBe(1)
        ->and($due->fresh()->status->equals(ApprovedConversion::class))->toBeTrue();
});

// CommissionCalculationResult Tests
test('CommissionCalculationResult can be created', function (): void {
    $result = new CommissionCalculationResult(
        baseCommissionMinor: 1000,
        volumeBonusMinor: 100,
        promotionBonusMinor: 50,
        finalCommissionMinor: 1150,
        appliedRules: ['rule-1', 'rule-2'],
        metadata: ['order_amount' => 10000]
    );

    expect($result)->toBeInstanceOf(CommissionCalculationResult::class);
    expect($result->baseCommissionMinor)->toBe(1000);
    expect($result->finalCommissionMinor)->toBe(1150);
});

test('CommissionCalculationResult getTotalBonusMinor calculates correctly', function (): void {
    $result = new CommissionCalculationResult(
        baseCommissionMinor: 1000,
        volumeBonusMinor: 100,
        promotionBonusMinor: 50,
        finalCommissionMinor: 1150
    );

    expect($result->getTotalBonusMinor())->toBe(150);
});

test('CommissionCalculationResult hasBonus returns true when has bonus', function (): void {
    $result = new CommissionCalculationResult(
        baseCommissionMinor: 1000,
        volumeBonusMinor: 100,
        promotionBonusMinor: 0,
        finalCommissionMinor: 1100
    );

    expect($result->hasBonus())->toBeTrue();
});

test('CommissionCalculationResult hasBonus returns false when no bonus', function (): void {
    $result = new CommissionCalculationResult(
        baseCommissionMinor: 1000,
        volumeBonusMinor: 0,
        promotionBonusMinor: 0,
        finalCommissionMinor: 1000
    );

    expect($result->hasBonus())->toBeFalse();
});

test('CommissionCalculationResult toArray returns correct structure', function (): void {
    $result = new CommissionCalculationResult(
        baseCommissionMinor: 1000,
        volumeBonusMinor: 100,
        promotionBonusMinor: 50,
        finalCommissionMinor: 1150,
        appliedRules: ['rule-1'],
        metadata: ['key' => 'value']
    );

    $array = $result->toArray();

    expect($array)->toBeArray();
    expect($array)->toHaveKey('base_commission_minor');
    expect($array)->toHaveKey('volume_bonus_minor');
    expect($array)->toHaveKey('promotion_bonus_minor');
    expect($array)->toHaveKey('final_commission_minor');
    expect($array)->toHaveKey('applied_rules');
    expect($array)->toHaveKey('metadata');
    expect($array['base_commission_minor'])->toBe(1000);
});

// CommissionRuleEngine Tests
test('CommissionRuleEngine calculate returns CommissionCalculationResult', function (): void {
    $engine = app(CommissionRuleEngine::class);

    $affiliate = Affiliate::create([
        'code' => 'ENGINE001',
        'name' => 'Engine Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $result = $engine->calculate($affiliate, 10000);

    expect($result)->toBeInstanceOf(CommissionCalculationResult::class);
    expect($result->baseCommissionMinor)->toBe(1000);
});

test('CommissionRuleEngine uses neutral revenue for volume tiers', function (): void {
    $engine = app(CommissionRuleEngine::class);

    $affiliate = Affiliate::create([
        'code' => 'ENGINE003',
        'name' => 'Volume Tier Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliateVolumeTier::create([
        'name' => 'Neutral Revenue Tier',
        'min_volume_minor' => 10000,
        'max_volume_minor' => null,
        'commission_rate_basis_points' => 1500,
        'period' => 'monthly',
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ENGINE-VOLUME-001',
        'total_minor' => 5000,
        'value_minor' => 12000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => QualifiedConversion::class,
        'occurred_at' => now()->startOfMonth()->addDay(),
    ]);

    $result = $engine->calculate($affiliate, 10000);

    expect($result->baseCommissionMinor)->toBe(1000)
        ->and($result->volumeBonusMinor)->toBe(500)
        ->and($result->finalCommissionMinor)->toBe(1500);
});

test('CommissionRuleEngine getApplicableRules filters and memoizes rules', function (): void {
    $engine = app(CommissionRuleEngine::class);

    $affiliate = Affiliate::create([
        'code' => 'ENGINE002',
        'name' => 'Rules Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $active = AffiliateCommissionRule::create([
        'name' => 'Active Rule',
        'conditions' => [],
        'rule_type' => 'product',
        'commission_type' => 'percentage',
        'commission_value' => 1000,
        'is_active' => true,
        'priority' => 1,
    ]);

    AffiliateCommissionRule::create([
        'name' => 'Inactive Rule',
        'conditions' => [],
        'rule_type' => 'product',
        'commission_type' => 'percentage',
        'commission_value' => 1000,
        'is_active' => false,
        'priority' => 1,
    ]);

    $first = $engine->getApplicableRules($affiliate, []);

    expect($first->pluck('id')->all())->toBe([$active->id]);

    // Repeat reads hit the memoized set.
    expect($engine->getApplicableRules($affiliate, []))->toBe($first);

    AffiliateCommissionRule::create([
        'name' => 'Late Rule',
        'conditions' => [],
        'rule_type' => 'product',
        'commission_type' => 'percentage',
        'commission_value' => 1000,
        'is_active' => true,
        'priority' => 1,
    ]);

    // Rule writes bust the cache so workers never price off stale rules.
    expect($engine->getApplicableRules($affiliate, []))->toHaveCount(2);
});
