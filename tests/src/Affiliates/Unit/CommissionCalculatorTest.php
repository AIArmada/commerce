<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\CommissionCalculator;
use AIArmada\Affiliates\States\Active;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['affiliates.currency.default' => 'USD']);
});

function createAffiliateForCommission(array $overrides = []): Affiliate
{
    return Affiliate::create(array_merge([
        'code' => 'COM-' . Str::uuid(),
        'name' => 'Commission Tester',
        'description' => null,
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 500,
        'currency' => 'USD',
    ], $overrides));
}

test('percentage commissions respect basis points precision', function (): void {
    $affiliate = createAffiliateForCommission([
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1250, // 12.50%
    ]);

    $commission = app(CommissionCalculator::class)->calculate($affiliate, 20_00);

    expect($commission)->toBe(250);
});

test('fixed commissions return static minor units', function (): void {
    $affiliate = createAffiliateForCommission([
        'commission_type' => CommissionType::Fixed,
        'commission_rate' => 1234,
    ]);

    $commission = app(CommissionCalculator::class)->calculate($affiliate, 99_99);

    expect($commission)->toBe(1234);
});
