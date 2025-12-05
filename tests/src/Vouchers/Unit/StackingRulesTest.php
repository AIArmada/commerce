<?php

declare(strict_types=1);

namespace Tests\Unit\Vouchers;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Stacking\Rules\MaxVouchersRule;
use AIArmada\Vouchers\Stacking\Rules\MutualExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\TypeRestrictionRule;

function createTestCart(): Cart
{
    $storage = new InMemoryStorage();

    return new Cart($storage, 'test-stacking-rules', events: null);
}

function createVoucherCondition(
    string $code,
    VoucherType $type = VoucherType::Percentage,
    int $value = 1000,
    array $metadata = []
): VoucherCondition {
    $data = VoucherData::fromArray([
        'id' => 'voucher-'.mb_strtolower($code),
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
        'status' => VoucherStatus::Active->value,
        'target_definition' => null,
        'metadata' => $metadata,
    ]);

    return new VoucherCondition($data);
}

describe('MaxVouchersRule', function (): void {
    it('allows adding voucher when under limit', function (): void {
        $rule = new MaxVouchersRule();
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect([createVoucherCondition('EXISTING')]);
        $cart = createTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => 3]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('denies adding voucher when at limit', function (): void {
        $rule = new MaxVouchersRule();
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect([
            createVoucherCondition('A'),
            createVoucherCondition('B'),
            createVoucherCondition('C'),
        ]);
        $cart = createTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => 3]);

        expect($decision->isDenied())->toBeTrue();
        expect($decision->reason)->toContain('Maximum of 3 voucher(s)');
    });

    it('allows unlimited vouchers with negative value', function (): void {
        $rule = new MaxVouchersRule();
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect(array_map(
            fn ($i) => createVoucherCondition("V{$i}"),
            range(1, 10)
        ));
        $cart = createTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['value' => -1]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('has correct type and priority', function (): void {
        $rule = new MaxVouchersRule();

        expect($rule->getType())->toBe('max_vouchers');
        expect($rule->getPriority())->toBe(10);
    });
});

describe('MutualExclusionRule', function (): void {
    it('allows vouchers from different exclusion groups', function (): void {
        $rule = new MutualExclusionRule();
        $newVoucher = createVoucherCondition('NEW', metadata: ['exclusion_groups' => ['group_a']]);
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['exclusion_groups' => ['group_b']])]);
        $cart = createTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['groups' => ['group_a', 'group_b']]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('denies vouchers from same exclusion group', function (): void {
        $rule = new MutualExclusionRule();
        $newVoucher = createVoucherCondition('NEW', metadata: ['exclusion_groups' => ['flash_sale']]);
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['exclusion_groups' => ['flash_sale']])]);
        $cart = createTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['groups' => ['flash_sale']]);

        expect($decision->isDenied())->toBeTrue();
        expect($decision->reason)->toContain('same exclusion group');
        expect($decision->conflictsWith)->not->toBeNull();
    });

    it('allows voucher without exclusion groups', function (): void {
        $rule = new MutualExclusionRule();
        $newVoucher = createVoucherCondition('NEW');
        $existing = collect([createVoucherCondition('EXISTING', metadata: ['exclusion_groups' => ['flash_sale']])]);
        $cart = createTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, ['groups' => ['flash_sale']]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('has correct type and priority', function (): void {
        $rule = new MutualExclusionRule();

        expect($rule->getType())->toBe('mutual_exclusion');
        expect($rule->getPriority())->toBe(30);
    });
});

describe('TypeRestrictionRule', function (): void {
    it('allows adding voucher type within limit', function (): void {
        $rule = new TypeRestrictionRule();
        $newVoucher = createVoucherCondition('NEW', VoucherType::Fixed, 1000);
        $existing = collect();
        $cart = createTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, [
            'max_per_type' => ['percentage' => 1, 'fixed' => 2],
        ]);

        expect($decision->isAllowed())->toBeTrue();
    });

    it('denies adding voucher type at limit', function (): void {
        $rule = new TypeRestrictionRule();
        $newVoucher = createVoucherCondition('NEW', VoucherType::Percentage, 1000);
        $existing = collect([createVoucherCondition('EXISTING', VoucherType::Percentage, 500)]);
        $cart = createTestCart();

        $decision = $rule->evaluate($newVoucher, $existing, $cart, [
            'max_per_type' => ['percentage' => 1, 'fixed' => 2],
        ]);

        expect($decision->isDenied())->toBeTrue();
        expect($decision->reason)->toContain('percentage');
        expect($decision->conflictsWith)->not->toBeNull();
    });

    it('has correct type and priority', function (): void {
        $rule = new TypeRestrictionRule();

        expect($rule->getType())->toBe('type_restriction');
        expect($rule->getPriority())->toBe(40);
    });
});
