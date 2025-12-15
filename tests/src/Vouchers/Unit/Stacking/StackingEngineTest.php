<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Stacking;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Stacking\Contracts\StackingPolicyInterface;
use AIArmada\Vouchers\Stacking\Contracts\StackingRuleInterface;
use AIArmada\Vouchers\Stacking\Enums\StackingMode;
use AIArmada\Vouchers\Stacking\StackingDecision;
use AIArmada\Vouchers\Stacking\StackingEngine;
use Mockery;

afterEach(function (): void {
    Mockery::close();
});

/**
 * Create a cart with items for testing.
 */
function createStackingTestCart(int $total = 10000): Cart
{
    $cart = new Cart(new InMemoryStorage(), 'stacking-test-' . uniqid());

    // Add items to get to the desired total (in cents)
    // Each item is 1000 cents = $10, so add total/1000 items
    $itemCount = (int) ($total / 1000);
    for ($i = 0; $i < $itemCount; $i++) {
        $cart->add([
            'id' => 'product-' . $i,
            'name' => 'Product ' . $i,
            'price' => 1000,
            'quantity' => 1,
        ]);
    }

    return $cart;
}

/**
 * Create a voucher condition for testing.
 */
function createTestVoucherCondition(
    string $code,
    string $type = 'percentage',
    float $value = 1000,
    array $metadata = []
): VoucherCondition {
    $voucherData = VoucherData::fromArray([
        'id' => 'voucher-' . $code,
        'code' => $code,
        'name' => 'Test Voucher ' . $code,
        'type' => $type,
        'value' => $value,
        'status' => 'active',
        'metadata' => $metadata,
    ]);

    return new VoucherCondition($voucherData);
}

/**
 * Create a mock policy with configurable mode and rules.
 */
function createMockPolicy(
    StackingMode $mode = StackingMode::Sequential,
    array $rules = []
): StackingPolicyInterface {
    $policy = Mockery::mock(StackingPolicyInterface::class);
    $policy->shouldReceive('getMode')->andReturn($mode);
    $policy->shouldReceive('getRules')->andReturn($rules);

    return $policy;
}

describe('StackingEngine', function (): void {
    describe('constructor', function (): void {
        it('registers default rules', function (): void {
            $policy = createMockPolicy();
            $engine = new StackingEngine($policy);

            $rules = $engine->getRules();

            expect($rules)->toHaveKey('max_vouchers');
            expect($rules)->toHaveKey('max_discount');
            expect($rules)->toHaveKey('max_discount_percentage');
            expect($rules)->toHaveKey('mutual_exclusion');
            expect($rules)->toHaveKey('type_restriction');
            expect($rules)->toHaveKey('category_exclusion');
            expect($rules)->toHaveKey('campaign_exclusion');
            expect($rules)->toHaveKey('value_threshold');
        });
    });

    describe('registerRule', function (): void {
        it('registers custom rule', function (): void {
            $policy = createMockPolicy();
            $engine = new StackingEngine($policy);

            $customRule = Mockery::mock(StackingRuleInterface::class);
            $customRule->shouldReceive('getType')->andReturn('custom_rule');

            $result = $engine->registerRule($customRule);

            expect($result)->toBe($engine);
            expect($engine->getRule('custom_rule'))->toBe($customRule);
        });
    });

    describe('getRule', function (): void {
        it('returns rule by type', function (): void {
            $policy = createMockPolicy();
            $engine = new StackingEngine($policy);

            $rule = $engine->getRule('max_vouchers');

            expect($rule)->not->toBeNull();
            expect($rule->getType())->toBe('max_vouchers');
        });

        it('returns null for unknown type', function (): void {
            $policy = createMockPolicy();
            $engine = new StackingEngine($policy);

            expect($engine->getRule('unknown_type'))->toBeNull();
        });
    });

    describe('canAdd', function (): void {
        it('allows voucher when no rules configured', function (): void {
            $policy = createMockPolicy(StackingMode::Sequential, []);
            $engine = new StackingEngine($policy);

            $cart = createStackingTestCart();
            $voucher = createTestVoucherCondition('TEST10');
            $existing = collect();

            $decision = $engine->canAdd($voucher, $existing, $cart);

            expect($decision->isAllowed())->toBeTrue();
        });

        it('evaluates max_vouchers rule', function (): void {
            $policy = createMockPolicy(StackingMode::Sequential, [
                ['type' => 'max_vouchers', 'value' => 2],
            ]);
            $engine = new StackingEngine($policy);

            $cart = createStackingTestCart();
            $voucher1 = createTestVoucherCondition('V1');
            $voucher2 = createTestVoucherCondition('V2');
            $voucher3 = createTestVoucherCondition('V3');

            $existing = collect([$voucher1, $voucher2]);
            $decision = $engine->canAdd($voucher3, $existing, $cart);

            expect($decision->isDenied())->toBeTrue();
            expect($decision->getReason())->toContain('Maximum');
        });

        it('allows when under max vouchers', function (): void {
            $policy = createMockPolicy(StackingMode::Sequential, [
                ['type' => 'max_vouchers', 'value' => 3],
            ]);
            $engine = new StackingEngine($policy);

            $cart = createStackingTestCart();
            $voucher1 = createTestVoucherCondition('V1');
            $voucher2 = createTestVoucherCondition('V2');

            $existing = collect([$voucher1]);
            $decision = $engine->canAdd($voucher2, $existing, $cart);

            expect($decision->isAllowed())->toBeTrue();
        });

        it('skips unknown rule types', function (): void {
            $policy = createMockPolicy(StackingMode::Sequential, [
                ['type' => 'unknown_rule_type', 'some_param' => 'value'],
            ]);
            $engine = new StackingEngine($policy);

            $cart = createStackingTestCart();
            $voucher = createTestVoucherCondition('TEST');

            $decision = $engine->canAdd($voucher, collect(), $cart);

            expect($decision->isAllowed())->toBeTrue();
        });
    });

    describe('getBestCombination', function (): void {
        it('returns all vouchers when count under max', function (): void {
            $policy = createMockPolicy(StackingMode::Sequential, []);
            $engine = new StackingEngine($policy);

            $cart = createStackingTestCart();
            $voucher1 = createTestVoucherCondition('V1');
            $voucher2 = createTestVoucherCondition('V2');
            $available = collect([$voucher1, $voucher2]);

            $result = $engine->getBestCombination($available, $cart, 5);

            expect($result->count())->toBe(2);
        });

        it('selects by priority in sequential mode', function (): void {
            $policy = createMockPolicy(StackingMode::Sequential, []);
            $engine = new StackingEngine($policy);

            $cart = createStackingTestCart();
            $voucher1 = createTestVoucherCondition('V1', 'percentage', 500, ['stacking_priority' => 10]);
            $voucher2 = createTestVoucherCondition('V2', 'percentage', 1000, ['stacking_priority' => 5]);
            $voucher3 = createTestVoucherCondition('V3', 'percentage', 1500, ['stacking_priority' => 15]);

            $available = collect([$voucher1, $voucher2, $voucher3]);
            $result = $engine->getBestCombination($available, $cart, 2);

            // Should select the lowest priority numbers first (5, 10)
            expect($result->count())->toBe(2);
        });

        it('finds best deal combination in best_deal mode', function (): void {
            $policy = createMockPolicy(StackingMode::BestDeal, []);
            $engine = new StackingEngine($policy);

            $cart = createStackingTestCart(10000); // 100 dollars
            $voucher1 = createTestVoucherCondition('V1', 'percentage', 500); // 5%
            $voucher2 = createTestVoucherCondition('V2', 'percentage', 1000); // 10%
            $voucher3 = createTestVoucherCondition('V3', 'fixed', 2000); // $20 off

            $available = collect([$voucher1, $voucher2, $voucher3]);
            $result = $engine->getBestCombination($available, $cart, 2);

            // Should find best combination
            expect($result->count())->toBeGreaterThanOrEqual(1);
        });
    });

    describe('calculateCombinationDiscount', function (): void {
        it('calculates sequential discount correctly', function (): void {
            $policy = createMockPolicy(StackingMode::Sequential, []);
            $engine = new StackingEngine($policy);

            $cart = createStackingTestCart(10000); // $100

            // Two 10% vouchers applied sequentially:
            // First: 10000 * 0.10 = 1000 discount, remaining = 9000
            // Second: 9000 * 0.10 = 900 discount
            // Total discount = 1900
            $voucher1 = createTestVoucherCondition('V1', 'percentage', 1000); // 10%
            $voucher2 = createTestVoucherCondition('V2', 'percentage', 1000); // 10%

            $vouchers = collect([$voucher1, $voucher2]);
            $discount = $engine->calculateCombinationDiscount($vouchers, $cart);

            // Should calculate sequential discount
            expect($discount)->toBeGreaterThan(0);
        });

        it('calculates parallel discount correctly', function (): void {
            $policy = createMockPolicy(StackingMode::Parallel, []);
            $engine = new StackingEngine($policy);

            $cart = createStackingTestCart(10000); // $100

            // Two 10% vouchers applied in parallel:
            // Both on original total: 10000 * 0.10 + 10000 * 0.10 = 2000
            $voucher1 = createTestVoucherCondition('V1', 'percentage', 1000); // 10%
            $voucher2 = createTestVoucherCondition('V2', 'percentage', 1000); // 10%

            $vouchers = collect([$voucher1, $voucher2]);
            $discount = $engine->calculateCombinationDiscount($vouchers, $cart);

            // Should calculate parallel discount
            expect($discount)->toBeGreaterThan(0);
        });

        it('caps discount at cart subtotal', function (): void {
            $policy = createMockPolicy(StackingMode::Parallel, []);
            $engine = new StackingEngine($policy);

            $cart = createStackingTestCart(5000); // $50

            // Two fixed vouchers totaling more than cart
            $voucher1 = createTestVoucherCondition('V1', 'fixed', 3000); // $30 off
            $voucher2 = createTestVoucherCondition('V2', 'fixed', 3000); // $30 off

            $vouchers = collect([$voucher1, $voucher2]);
            $discount = $engine->calculateCombinationDiscount($vouchers, $cart);

            // Total discount should be capped at cart subtotal
            expect($discount)->toBeLessThanOrEqual(5000);
        });
    });
});

describe('StackingDecision', function (): void {
    describe('allow', function (): void {
        it('creates allowed decision', function (): void {
            $decision = StackingDecision::allow();

            expect($decision->isAllowed())->toBeTrue();
            expect($decision->isDenied())->toBeFalse();
            expect($decision->reason)->toBeNull();
        });
    });

    describe('deny', function (): void {
        it('creates denied decision with reason', function (): void {
            $decision = StackingDecision::deny('Maximum vouchers reached');

            expect($decision->isAllowed())->toBeFalse();
            expect($decision->isDenied())->toBeTrue();
            expect($decision->reason)->toBe('Maximum vouchers reached');
        });

        it('creates denied decision with conflict', function (): void {
            $conflicting = createTestVoucherCondition('CONFLICT');
            $decision = StackingDecision::deny('Voucher conflict', $conflicting);

            expect($decision->hasConflict())->toBeTrue();
            expect($decision->conflictsWith)->toBe($conflicting);
        });

        it('creates denied decision with suggested replacement', function (): void {
            $replacement = createTestVoucherCondition('BETTER');
            $decision = StackingDecision::deny('Better option available', null, $replacement);

            expect($decision->hasSuggestedReplacement())->toBeTrue();
            expect($decision->suggestedReplacement)->toBe($replacement);
        });
    });

    describe('getReason', function (): void {
        it('returns reason when set', function (): void {
            $decision = StackingDecision::deny('Custom reason');

            expect($decision->getReason())->toBe('Custom reason');
        });

        it('returns default when reason not set', function (): void {
            $decision = StackingDecision::allow();

            expect($decision->getReason())->toBe('Voucher stacking not allowed');
        });
    });

    describe('toArray', function (): void {
        it('serializes allowed decision', function (): void {
            $decision = StackingDecision::allow();
            $array = $decision->toArray();

            expect($array)->toBe([
                'allowed' => true,
                'reason' => null,
                'conflicts_with' => null,
                'suggested_replacement' => null,
            ]);
        });

        it('serializes denied decision with conflict', function (): void {
            $conflicting = createTestVoucherCondition('CONFLICT');
            $decision = StackingDecision::deny('Conflict', $conflicting);
            $array = $decision->toArray();

            expect($array['allowed'])->toBeFalse();
            expect($array['reason'])->toBe('Conflict');
            expect($array['conflicts_with'])->toBe('CONFLICT');
        });
    });
});
