<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateProgram;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// AffiliateCommissionRule Tests
test('AffiliateCommissionRule can be created with required fields', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Commission Rule Program',
        'slug' => 'commission-rule-program',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $rule = AffiliateCommissionRule::create([
        'program_id' => $program->id,
        'name' => 'Product Bonus',
        'rule_type' => CommissionRuleType::Product,
        'priority' => 100,
        'conditions' => ['product_id' => 'PROD_123'],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 1500,
        'is_active' => true,
    ]);

    expect($rule)->toBeInstanceOf(AffiliateCommissionRule::class);
    expect($rule->name)->toBe('Product Bonus');
    expect($rule->priority)->toBe(100);
});

test('AffiliateCommissionRule has program relationship', function (): void {
    $rule = new AffiliateCommissionRule;

    expect($rule->program())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateCommissionRule isActive returns true when all conditions met', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    expect($rule->isActive())->toBeTrue();
});

test('AffiliateCommissionRule isActive returns false when is_active is false', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => false,
    ]);

    expect($rule->isActive())->toBeFalse();
});

test('AffiliateCommissionRule isActive returns false when starts_at is in future', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'starts_at' => now()->addDay(),
    ]);

    expect($rule->isActive())->toBeFalse();
});

test('AffiliateCommissionRule isActive returns false when ends_at is in past', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'ends_at' => now()->subDay(),
    ]);

    expect($rule->isActive())->toBeFalse();
});

test('AffiliateCommissionRule matches returns true when conditions are met', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['product_id' => 'PROD_123'],
    ]);

    expect($rule->matches(['product_id' => 'PROD_123']))->toBeTrue();
});

test('AffiliateCommissionRule matches returns false when conditions not met', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['product_id' => 'PROD_123'],
    ]);

    expect($rule->matches(['product_id' => 'PROD_456']))->toBeFalse();
});

test('AffiliateCommissionRule matches handles in condition', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['category' => ['in' => ['electronics', 'computers']]],
    ]);

    expect($rule->matches(['category' => 'electronics']))->toBeTrue();
    expect($rule->matches(['category' => 'clothing']))->toBeFalse();
});

test('AffiliateCommissionRule matches handles not_in condition', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['country' => ['not_in' => ['US', 'CA']]],
    ]);

    expect($rule->matches(['country' => 'UK']))->toBeTrue();
    expect($rule->matches(['country' => 'US']))->toBeFalse();
});

test('AffiliateCommissionRule matches handles min condition', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['amount' => ['min' => 10000]],
    ]);

    expect($rule->matches(['amount' => 15000]))->toBeTrue();
    expect($rule->matches(['amount' => 5000]))->toBeFalse();
});

test('AffiliateCommissionRule matches handles max condition', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['quantity' => ['max' => 10]],
    ]);

    expect($rule->matches(['quantity' => 5]))->toBeTrue();
    expect($rule->matches(['quantity' => 15]))->toBeFalse();
});

test('AffiliateCommissionRule matches handles equals condition', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['tier' => ['equals' => 'gold']],
    ]);

    expect($rule->matches(['tier' => 'gold']))->toBeTrue();
    expect($rule->matches(['tier' => 'silver']))->toBeFalse();
});

test('AffiliateCommissionRule calculateCommission for percentage type', function (): void {
    $rule = new AffiliateCommissionRule([
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 1000, // 10% in basis points
    ]);

    // 10000 cents * 10% = 1000 cents
    expect($rule->calculateCommission(10000))->toBe(1000);
});

test('AffiliateCommissionRule calculateCommission for fixed type', function (): void {
    $rule = new AffiliateCommissionRule([
        'commission_type' => CommissionType::Fixed,
        'commission_value' => 500, // 500 cents fixed
    ]);

    expect($rule->calculateCommission(10000))->toBe(500);
});

test('AffiliateCommissionRule calculateCommission handles persisted string commission type', function (): void {
    $rule = new AffiliateCommissionRule;
    $rule->setRawAttributes([
        'commission_type' => CommissionType::Percentage->value,
        'commission_value' => 2500,
    ], true);

    expect($rule->calculateCommission(20000))->toBe(5000);
});

test('AffiliateCommissionRule scopeActive filters correctly', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Active Rule Test',
        'slug' => 'active-rule-test',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    AffiliateCommissionRule::create([
        'program_id' => $program->id,
        'name' => 'Active Rule',
        'rule_type' => CommissionRuleType::Product,
        'priority' => 100,
        'conditions' => [],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 1000,
        'is_active' => true,
    ]);

    AffiliateCommissionRule::create([
        'program_id' => $program->id,
        'name' => 'Inactive Rule',
        'rule_type' => CommissionRuleType::Product,
        'priority' => 50,
        'conditions' => [],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 500,
        'is_active' => false,
    ]);

    $activeRules = AffiliateCommissionRule::active()->pluck('name');

    expect($activeRules)->toContain('Active Rule');
    expect($activeRules)->not->toContain('Inactive Rule');
});

test('AffiliateCommissionRule scopeOrdered orders by priority descending', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Ordered Rule Test',
        'slug' => 'ordered-rule-test',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    AffiliateCommissionRule::create([
        'program_id' => $program->id,
        'name' => 'Low Priority',
        'rule_type' => CommissionRuleType::Product,
        'priority' => 10,
        'conditions' => [],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 500,
        'is_active' => true,
    ]);

    AffiliateCommissionRule::create([
        'program_id' => $program->id,
        'name' => 'High Priority',
        'rule_type' => CommissionRuleType::Promotion,
        'priority' => 100,
        'conditions' => [],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 2000,
        'is_active' => true,
    ]);

    $orderedRules = AffiliateCommissionRule::ordered()->pluck('name');

    expect($orderedRules->first())->toBe('High Priority');
    expect($orderedRules->last())->toBe('Low Priority');
});
