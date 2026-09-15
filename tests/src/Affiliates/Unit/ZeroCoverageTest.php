<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\MatureConversion;
use AIArmada\Affiliates\Actions\Conversions\ProcessConversionMaturity;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Services\Commissions\CommissionCalculationResult;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\QualifiedConversion;
use Illuminate\Support\Collection;

// MatureConversion Action Tests
test('MatureConversion can be instantiated', function (): void {
    $action = app(MatureConversion::class);

    expect($action)->toBeInstanceOf(MatureConversion::class);
});

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
test('ProcessConversionMaturity can be instantiated', function (): void {
    $action = app(ProcessConversionMaturity::class);

    expect($action)->toBeInstanceOf(ProcessConversionMaturity::class);
});

test('ProcessConversionMaturity processes qualified conversions', function (): void {
    $action = app(ProcessConversionMaturity::class);

    $result = $action->handle();

    expect($result)->toBeInt();
    expect($result)->toBeGreaterThanOrEqual(0);
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
test('CommissionRuleEngine can be instantiated', function (): void {
    $engine = app(CommissionRuleEngine::class);

    expect($engine)->toBeInstanceOf(CommissionRuleEngine::class);
});

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
    expect($result->baseCommissionMinor)->toBeGreaterThanOrEqual(0);
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

test('CommissionRuleEngine getApplicableRules returns collection', function (): void {
    $engine = app(CommissionRuleEngine::class);

    $affiliate = Affiliate::create([
        'code' => 'ENGINE002',
        'name' => 'Rules Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $rules = $engine->getApplicableRules($affiliate, []);

    expect($rules)->toBeInstanceOf(Collection::class);
});

test('CommissionRuleEngine clearCache works', function (): void {
    $engine = app(CommissionRuleEngine::class);

    // Call clearCache and verify no errors
    $engine->clearCache();

    expect(true)->toBeTrue(); // Just verify no exception was thrown
});
