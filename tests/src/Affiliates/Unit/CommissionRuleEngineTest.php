<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionCalculationResult;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionRuleCalculation;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\QualifiedConversion;

// CommissionRuleCalculation Tests
test('CommissionRuleCalculation calculate returns CommissionCalculationResult', function (): void {
    $engine = app(CommissionRuleCalculation::class);

    $affiliate = Affiliate::create([
        'code' => 'ENGINE001',
        'name' => 'Engine Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $result = $engine->calculate((string) $affiliate->getKey(), 10000);

    expect($result)->toBeInstanceOf(CommissionCalculationResult::class);
    expect($result->baseCommissionMinor)->toBe(1000);
});

test('CommissionRuleCalculation uses neutral revenue for volume tiers', function (): void {
    $engine = app(CommissionRuleCalculation::class);

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
        'currency' => 'USD',
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

    $result = $engine->calculate((string) $affiliate->getKey(), 10000);

    expect($result->baseCommissionMinor)->toBe(1000)
        ->and($result->volumeBonusMinor)->toBe(500)
        ->and($result->finalCommissionMinor)->toBe(1500);
});

test('CommissionRuleCalculation getApplicableRules filters rules and always reads current state', function (): void {
    $engine = app(CommissionRuleCalculation::class);

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

    $first = $engine->calculate((string) $affiliate->getKey(), 10000)->appliedRules;

    expect($first)->toBe([$active->id]);

    expect($engine->calculate((string) $affiliate->getKey(), 10000)->appliedRules)->toBe($first);

    AffiliateCommissionRule::create([
        'name' => 'Late Rule',
        'conditions' => [],
        'rule_type' => 'product',
        'commission_type' => 'percentage',
        'commission_value' => 1000,
        'is_active' => true,
        'priority' => 1,
    ]);

    // A subsequent calculation observes new rules without a mutable tenant-shared cache.
    expect($engine->calculate((string) $affiliate->getKey(), 10000)->appliedRules)->toHaveCount(2);
});
