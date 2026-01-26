<?php

declare(strict_types=1);

namespace Tests\Unit\Vouchers;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Stacking\Rules\CampaignExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\CategoryExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\MaxDiscountPercentageRule;
use AIArmada\Vouchers\Stacking\Rules\MaxDiscountRule;
use AIArmada\Vouchers\Stacking\Rules\MaxVouchersRule;
use AIArmada\Vouchers\Stacking\Rules\MutualExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\TypeRestrictionRule;
use AIArmada\Vouchers\Stacking\Rules\ValueThresholdRule;
use AIArmada\Vouchers\States\Active;

function createStackingTestCart(int $subtotalCents = 10000): Cart
{
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-stacking-rules', events: null);

    // Add an item to give the cart a subtotal
    $cart->add([
        'id' => 'test-item',
        'name' => 'Test Product',
        'price' => $subtotalCents,
        'quantity' => 1,
    ]);

    return $cart;
}

function createEmptyStackingTestCart(): Cart
{
    $storage = new InMemoryStorage;

    return new Cart($storage, 'test-stacking-empty', events: null);
}

function createVoucherCondition(
    string $code,
    VoucherType $type = VoucherType::Percentage,
    int $value = 1000,
    array $metadata = []
): VoucherCondition {
    $data = VoucherData::fromArray([
        'id' => 'voucher-' . mb_strtolower($code),
        'code' => $code,
        'name' => "Test Voucher {$code}",
        'description' => null,
        'type' => $type->value,
        'value' => $value,
        'currency' => 'MYR',
        'min_cart_value' => null,
        'max_discount' => null,
        'usage_limit' => null,
        'usage_limit_per_user' => null,
        'allows_manual_redemption' => false,
        'owner_id' => null,
        'owner_type' => null,
        'starts_at' => null,
        'expires_at' => null,
        'status' => Active::class,
        'target_definition' => null,
        'metadata' => $metadata,
    ]);

    return new VoucherCondition($data);
}

describe('MaxVouchersRule', function (): void {
    it('allows adding voucher when under limit', function (): void {
        $rule = new MaxVouchersRule;
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect([createVoucherCondition('EXISTING')]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => 3]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('denies adding voucher when at limit', function (): void {
        $rule = new MaxVouchersRule;
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect([
            createVoucherCondition('A'),
            createVoucherCondition('B'),
            createVoucherCondition('C'),
        ]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => 3]);

        expect($decision->isDenied())->toBeTrue();
        expect($decision->reason)->toContain('Maximum of 3 voucher(s)');
    });

    it('allows unlimited vouchers with negative value', function (): void {
        $rule = new MaxVouchersRule;
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect(array_map(
            fn ($i) => createVoucherCondition("V{$i}"),
            range(1, 10)
        ));
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => -1]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('has correct type and priority', function (): void {
        $rule = new MaxVouchersRule;

        expect($rule->getType())->toBe('max_vouchers');
        expect($rule->getPriority())->toBe(10);
    });
});

describe('MutualExclusionRule', function (): void {
    it('allows vouchers from different exclusion groups', function (): void {
        $rule = new MutualExclusionRule;
        $newVoucher = createVoucherCondition('NEW', metadata: ['exclusion_groups' => ['group_a']]);
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['exclusion_groups' => ['group_b']])]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['groups' => ['group_a', 'group_b']]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('denies vouchers from same exclusion group', function (): void {
        $rule = new MutualExclusionRule;
        $newVoucher = createVoucherCondition('NEW', metadata: ['exclusion_groups' => ['flash_sale']]);
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['exclusion_groups' => ['flash_sale']])]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['groups' => ['flash_sale']]);

        expect($decision->isDenied())->toBeTrue();
        expect($decision->reason)->toContain('same exclusion group');
        expect($decision->conflictsWith)->not->toBeNull();
    });

    it('allows voucher without exclusion groups', function (): void {
        $rule = new MutualExclusionRule;
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['exclusion_groups' => ['flash_sale']])]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['groups' => ['flash_sale']]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('has correct type and priority', function (): void {
        $rule = new MutualExclusionRule;

        expect($rule->getType())->toBe('mutual_exclusion');
        expect($rule->getPriority())->toBe(30);
    });
});

describe('TypeRestrictionRule', function (): void {
    it('allows adding voucher type within limit', function (): void {
        $rule = new TypeRestrictionRule;
        $newVoucher = createVoucherCondition('NEW', VoucherType::Fixed, 1000);
        $existing = collect();
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, [
            'max_per_type' => ['percentage' => 1, 'fixed' => 2],
        ]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('denies adding voucher type at limit', function (): void {
        $rule = new TypeRestrictionRule;
        $newVoucher = createVoucherCondition('NEW', VoucherType::Percentage, 1000);
        $existing = collect([createVoucherCondition('EXISTING', VoucherType::Percentage, 500)]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, [
            'max_per_type' => ['percentage' => 1, 'fixed' => 2],
        ]);

        expect($decision->isDenied())->toBeTrue();
        expect($decision->reason)->toContain('percentage');
        expect($decision->conflictsWith)->not->toBeNull();
    });

    it('has correct type and priority', function (): void {
        $rule = new TypeRestrictionRule;

        expect($rule->getType())->toBe('type_restriction');
        expect($rule->getPriority())->toBe(40);
    });
});

describe('MaxDiscountRule', function (): void {
    it('allows discount under maximum', function (): void {
        $rule = new MaxDiscountRule;
        // 10% of 10000 cents = 1000 cents discount
        $newVoucher = createVoucherCondition('NEW', VoucherType::Percentage, 1000);
        $existing = collect();
        $cart = createStackingTestCart(10000);

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => 2000]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('denies discount exceeding maximum', function (): void {
        $rule = new MaxDiscountRule;
        // 50% of 10000 cents = 5000 cents discount
        $newVoucher = createVoucherCondition('NEW', VoucherType::Percentage, 5000);
        $existing = collect();
        $cart = createStackingTestCart(10000);

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => 2000]);

        expect($decision->isDenied())->toBeTrue()
            ->and($decision->reason)->toContain('exceed maximum');
    });

    it('allows when no maximum configured', function (): void {
        $rule = new MaxDiscountRule;
        $newVoucher = createVoucherCondition('NEW', VoucherType::Percentage, 5000);
        $existing = collect();
        $cart = createStackingTestCart(10000);

        $decision = $rule->evaluate($newVoucher, $existing, $cart, []);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('considers existing vouchers discount', function (): void {
        $rule = new MaxDiscountRule;
        // Each is 10% = 1000 cents
        $newVoucher = createVoucherCondition('NEW', VoucherType::Percentage, 1000);
        $existing = collect([createVoucherCondition('EXISTING', VoucherType::Percentage, 1000)]);
        $cart = createStackingTestCart(10000);

        // Total would be 2000 cents, max is 1500
        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => 1500]);

        expect($decision->isDenied())->toBeTrue();
    });

    it('has correct type and priority', function (): void {
        $rule = new MaxDiscountRule;

        expect($rule->getType())->toBe('max_discount');
        expect($rule->getPriority())->toBe(20);
    });
});

describe('MaxDiscountPercentageRule', function (): void {
    it('allows discount under percentage limit', function (): void {
        $rule = new MaxDiscountPercentageRule;
        // 10% discount
        $newVoucher = createVoucherCondition('NEW', VoucherType::Percentage, 1000);
        $existing = collect();
        $cart = createStackingTestCart(10000);

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => 50]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('denies discount exceeding percentage limit', function (): void {
        $rule = new MaxDiscountPercentageRule;
        // 60% discount
        $newVoucher = createVoucherCondition('NEW', VoucherType::Percentage, 6000);
        $existing = collect();
        $cart = createStackingTestCart(10000);

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => 50]);

        expect($decision->isDenied())->toBeTrue()
            ->and($decision->reason)->toContain('50%');
    });

    it('allows when percentage is invalid', function (): void {
        $rule = new MaxDiscountPercentageRule;
        $newVoucher = createVoucherCondition('NEW', VoucherType::Percentage, 6000);
        $existing = collect();
        $cart = createStackingTestCart(10000);

        // Invalid percentage (>100)
        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => 150]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('allows when cart subtotal is zero', function (): void {
        $rule = new MaxDiscountPercentageRule;
        $newVoucher = createVoucherCondition('NEW', VoucherType::Percentage, 1000);
        $existing = collect();
        $cart = createEmptyStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => 50]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('has correct type and priority', function (): void {
        $rule = new MaxDiscountPercentageRule;

        expect($rule->getType())->toBe('max_discount_percentage');
        expect($rule->getPriority())->toBe(25);
    });
});

describe('ValueThresholdRule', function (): void {
    it('allows stacking when cart meets minimum value', function (): void {
        $rule = new ValueThresholdRule;
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect([createVoucherCondition('EXISTING')]);
        $cart = createStackingTestCart(10000);

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['minimum' => 5000]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('denies stacking when cart below minimum value', function (): void {
        $rule = new ValueThresholdRule;
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect([createVoucherCondition('EXISTING')]);
        $cart = createStackingTestCart(3000);

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['minimum' => 5000]);

        expect($decision->isDenied())->toBeTrue()
            ->and($decision->reason)->toContain('at least');
    });

    it('allows first voucher without minimum check', function (): void {
        $rule = new ValueThresholdRule;
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect(); // No existing vouchers
        $cart = createStackingTestCart(1000);

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['minimum' => 5000]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('allows when no minimum configured', function (): void {
        $rule = new ValueThresholdRule;
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect([createVoucherCondition('EXISTING')]);
        $cart = createStackingTestCart(1000);

        $decision = $rule->evaluate($newVoucher, $existing, $cart, []);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('has correct type and priority', function (): void {
        $rule = new ValueThresholdRule;

        expect($rule->getType())->toBe('value_threshold');
        expect($rule->getPriority())->toBe(5);
    });
});

describe('CampaignExclusionRule', function (): void {
    it('allows vouchers from different campaigns', function (): void {
        $rule = new CampaignExclusionRule;
        $newVoucher = createVoucherCondition('NEW', metadata: ['campaign_id' => 'campaign-a']);
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['campaign_id' => 'campaign-b'])]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, []);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('denies vouchers from same campaign by default', function (): void {
        $rule = new CampaignExclusionRule;
        $newVoucher = createVoucherCondition('NEW', metadata: ['campaign_id' => 'campaign-a']);
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['campaign_id' => 'campaign-a'])]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, []);

        expect($decision->isDenied())->toBeTrue();
    });

    it('allows vouchers without campaign id', function (): void {
        $rule = new CampaignExclusionRule;
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['campaign_id' => 'campaign-a'])]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, []);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('has correct type and priority', function (): void {
        $rule = new CampaignExclusionRule;

        expect($rule->getType())->toBe('campaign_exclusion');
        expect($rule->getPriority())->toBe(55);
    });
});

describe('CategoryExclusionRule', function (): void {
    it('allows vouchers targeting different categories', function (): void {
        $rule = new CategoryExclusionRule;
        $newVoucher = createVoucherCondition('NEW', metadata: ['target_categories' => ['electronics']]);
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['target_categories' => ['clothing']])]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, []);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('denies vouchers targeting same category by default', function (): void {
        $rule = new CategoryExclusionRule;
        $newVoucher = createVoucherCondition('NEW', metadata: ['target_categories' => ['electronics']]);
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['target_categories' => ['electronics']])]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, []);

        expect($decision->isDenied())->toBeTrue();
    });

    it('allows vouchers without target categories', function (): void {
        $rule = new CategoryExclusionRule;
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['target_categories' => ['electronics']])]);
        $cart = createStackingTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, []);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('has correct type and priority', function (): void {
        $rule = new CategoryExclusionRule;

        expect($rule->getType())->toBe('category_exclusion');
        expect($rule->getPriority())->toBe(50);
    });
});
