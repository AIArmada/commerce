<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Stacking;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Stacking\Enums\StackingMode;
use AIArmada\Vouchers\Stacking\Enums\StackingRuleType;
use AIArmada\Vouchers\Stacking\StackingPolicy;
use Illuminate\Support\Collection;

/**
 * Create a cart for stacking policy tests.
 */
function createPolicyTestCart(): Cart
{
    $cart = new Cart(new InMemoryStorage(), 'policy-test-' . uniqid());
    $cart->add([
        'id' => 'product-1',
        'name' => 'Test Product',
        'price' => 10000,
        'quantity' => 2,
    ]);

    return $cart;
}

/**
 * Create a voucher condition for testing.
 */
function createPolicyVoucherCondition(string $code, VoucherType $type = VoucherType::Percentage, int $value = 10, array $metadata = []): VoucherCondition
{
    $voucherData = VoucherData::fromArray([
        'id' => 'voucher-' . uniqid(),
        'code' => $code,
        'type' => $type->value,
        'value' => $value,
        'usage_limit' => 100,
        'usage_count' => 0,
        'is_active' => true,
        'expires_at' => null,
        'starts_at' => null,
        'metadata' => $metadata,
    ]);

    return new VoucherCondition($voucherData);
}

describe('StackingPolicy', function (): void {
    describe('static factories', function (): void {
        it('creates default policy', function (): void {
            $policy = StackingPolicy::default();

            expect($policy->getMode())->toBe(StackingMode::Sequential);
            expect($policy->getRules())->not->toBeEmpty();
            expect($policy->isAutoOptimizeEnabled())->toBeFalse();
            expect($policy->isAutoReplaceEnabled())->toBeTrue();
        });

        it('creates single voucher policy', function (): void {
            $policy = StackingPolicy::singleVoucher();

            expect($policy->getMode())->toBe(StackingMode::None);
            expect($policy->isAutoReplaceEnabled())->toBeTrue();
        });

        it('creates unlimited policy', function (): void {
            $policy = StackingPolicy::unlimited();

            expect($policy->getMode())->toBe(StackingMode::Sequential);
            expect($policy->getRules())->toBe([]);
            expect($policy->isAutoOptimizeEnabled())->toBeTrue();
            expect($policy->isAutoReplaceEnabled())->toBeFalse();
        });

        it('creates from config array', function (): void {
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
            expect($policy->getRules())->toBe($config['rules']);
            expect($policy->isAutoOptimizeEnabled())->toBeTrue();
            expect($policy->isAutoReplaceEnabled())->toBeFalse();
        });

        it('handles invalid mode in config', function (): void {
            $config = [
                'mode' => 'invalid_mode',
            ];

            $policy = StackingPolicy::fromConfig($config);

            expect($policy->getMode())->toBe(StackingMode::Sequential); // Default
        });

        it('handles empty config', function (): void {
            $policy = StackingPolicy::fromConfig([]);

            expect($policy->getMode())->toBe(StackingMode::Sequential);
            expect($policy->getRules())->toBe([]);
            expect($policy->isAutoOptimizeEnabled())->toBeFalse();
            expect($policy->isAutoReplaceEnabled())->toBeTrue();
        });
    });

    describe('constructor and getters', function (): void {
        it('creates with custom parameters', function (): void {
            $rules = [['type' => 'max_vouchers', 'value' => 2]];
            $policy = new StackingPolicy(
                mode: StackingMode::Parallel,
                rules: $rules,
                autoOptimize: true,
                autoReplace: false,
            );

            expect($policy->getMode())->toBe(StackingMode::Parallel);
            expect($policy->getRules())->toBe($rules);
            expect($policy->isAutoOptimizeEnabled())->toBeTrue();
            expect($policy->isAutoReplaceEnabled())->toBeFalse();
        });
    });

    describe('fluent builders', function (): void {
        it('adds rule with addRule', function (): void {
            $policy = new StackingPolicy();
            $rule = ['type' => 'max_vouchers', 'value' => 5];

            $result = $policy->addRule($rule);

            expect($result)->toBe($policy);
            expect($policy->getRules())->toContain($rule);
        });

        it('sets mode with withMode', function (): void {
            $policy = new StackingPolicy();

            $result = $policy->withMode(StackingMode::Parallel);

            expect($result)->toBe($policy);
            expect($policy->getMode())->toBe(StackingMode::Parallel);
        });

        it('sets auto optimize with withAutoOptimize', function (): void {
            $policy = new StackingPolicy();

            $result = $policy->withAutoOptimize(true);

            expect($result)->toBe($policy);
            expect($policy->isAutoOptimizeEnabled())->toBeTrue();
        });

        it('sets auto replace with withAutoReplace', function (): void {
            $policy = new StackingPolicy(autoReplace: true);

            $result = $policy->withAutoReplace(false);

            expect($result)->toBe($policy);
            expect($policy->isAutoReplaceEnabled())->toBeFalse();
        });
    });

    describe('canAdd', function (): void {
        it('denies when mode is None and vouchers exist', function (): void {
            $policy = StackingPolicy::singleVoucher();
            $cart = createPolicyTestCart();
            $newVoucher = createPolicyVoucherCondition('NEW10');
            $existingVouchers = collect([createPolicyVoucherCondition('EXISTING')]);

            $decision = $policy->canAdd($newVoucher, $existingVouchers, $cart);

            expect($decision->isAllowed())->toBeFalse();
            expect($decision->getReason())->toContain('one voucher');
        });

        it('allows when mode is None and no vouchers exist', function (): void {
            $policy = StackingPolicy::singleVoucher();
            $cart = createPolicyTestCart();
            $newVoucher = createPolicyVoucherCondition('NEW10');
            $existingVouchers = collect([]);

            $decision = $policy->canAdd($newVoucher, $existingVouchers, $cart);

            expect($decision->isAllowed())->toBeTrue();
        });

        it('allows when mode is Sequential and under limit', function (): void {
            $policy = new StackingPolicy(
                mode: StackingMode::Sequential,
                rules: [['type' => StackingRuleType::MaxVouchers->value, 'value' => 3]],
            );
            $cart = createPolicyTestCart();
            $newVoucher = createPolicyVoucherCondition('NEW10');
            $existingVouchers = collect([createPolicyVoucherCondition('EXISTING')]);

            $decision = $policy->canAdd($newVoucher, $existingVouchers, $cart);

            expect($decision->isAllowed())->toBeTrue();
        });
    });

    describe('resolveConflict', function (): void {
        it('returns empty collection when no vouchers', function (): void {
            $policy = StackingPolicy::default();
            $cart = createPolicyTestCart();

            $result = $policy->resolveConflict(collect([]), $cart);

            expect($result)->toBeEmpty();
        });

        it('returns vouchers when under max limit', function (): void {
            $policy = new StackingPolicy(
                rules: [['type' => StackingRuleType::MaxVouchers->value, 'value' => 5]],
            );
            $cart = createPolicyTestCart();
            $vouchers = collect([
                createPolicyVoucherCondition('V1'),
                createPolicyVoucherCondition('V2'),
            ]);

            $result = $policy->resolveConflict($vouchers, $cart);

            expect($result->count())->toBe(2);
        });

        it('optimizes when over max limit', function (): void {
            $policy = new StackingPolicy(
                rules: [['type' => StackingRuleType::MaxVouchers->value, 'value' => 2]],
            );
            $cart = createPolicyTestCart();
            $vouchers = collect([
                createPolicyVoucherCondition('V1', VoucherType::Fixed, 500),
                createPolicyVoucherCondition('V2', VoucherType::Fixed, 1000),
                createPolicyVoucherCondition('V3', VoucherType::Fixed, 200),
            ]);

            $result = $policy->resolveConflict($vouchers, $cart);

            expect($result->count())->toBeLessThanOrEqual(2);
        });
    });

    describe('getApplicationOrder', function (): void {
        it('returns single voucher unchanged', function (): void {
            $policy = StackingPolicy::default();
            $cart = createPolicyTestCart();
            $voucher = createPolicyVoucherCondition('SINGLE');
            $vouchers = collect([$voucher]);

            $result = $policy->getApplicationOrder($vouchers, $cart);

            expect($result->count())->toBe(1);
            expect($result->first())->toBe($voucher);
        });

        it('returns empty collection unchanged', function (): void {
            $policy = StackingPolicy::default();
            $cart = createPolicyTestCart();

            $result = $policy->getApplicationOrder(collect([]), $cart);

            expect($result)->toBeEmpty();
        });

        it('sorts by stacking priority metadata', function (): void {
            $policy = StackingPolicy::default();
            $cart = createPolicyTestCart();

            $lowPriority = createPolicyVoucherCondition('LOW', VoucherType::Fixed, 100, ['stacking_priority' => 200]);
            $highPriority = createPolicyVoucherCondition('HIGH', VoucherType::Fixed, 100, ['stacking_priority' => 10]);

            $vouchers = collect([$lowPriority, $highPriority]);

            $result = $policy->getApplicationOrder($vouchers, $cart);

            expect($result->first()->getVoucherCode())->toBe('HIGH');
        });

        it('uses default priority 100 when not set', function (): void {
            $policy = StackingPolicy::default();
            $cart = createPolicyTestCart();

            $noPriority = createPolicyVoucherCondition('DEFAULT', VoucherType::Fixed, 100);
            $lowPriority = createPolicyVoucherCondition('LOW', VoucherType::Fixed, 100, ['stacking_priority' => 150]);

            $vouchers = collect([$lowPriority, $noPriority]);

            $result = $policy->getApplicationOrder($vouchers, $cart);

            // DEFAULT (100) should come before LOW (150)
            expect($result->first()->getVoucherCode())->toBe('DEFAULT');
        });
    });
});
