<?php

declare(strict_types=1);

use AIArmada\Affiliates\Services\Commissions\CommissionCalculationResult;

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
