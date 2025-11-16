<?php

declare(strict_types=1);

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;

it('can get voucher status labels', function (): void {
    expect(VoucherStatus::Active->label())->toBe('Active')
        ->and(VoucherStatus::Paused->label())->toBe('Paused')
        ->and(VoucherStatus::Expired->label())->toBe('Expired')
        ->and(VoucherStatus::Depleted->label())->toBe('Depleted');
});

it('can get voucher status descriptions', function (): void {
    expect(VoucherStatus::Active->description())->toBe('Voucher can be used')
        ->and(VoucherStatus::Paused->description())->toBe('Voucher temporarily disabled')
        ->and(VoucherStatus::Expired->description())->toBe('Voucher past expiry date')
        ->and(VoucherStatus::Depleted->description())->toBe('Voucher usage limit reached');
});

it('can check if voucher status can be used', function (): void {
    expect(VoucherStatus::Active->canBeUsed())->toBeTrue()
        ->and(VoucherStatus::Paused->canBeUsed())->toBeFalse()
        ->and(VoucherStatus::Expired->canBeUsed())->toBeFalse()
        ->and(VoucherStatus::Depleted->canBeUsed())->toBeFalse();
});

it('can get voucher type labels', function (): void {
    expect(VoucherType::Percentage->label())->toBe('Percentage Discount')
        ->and(VoucherType::Fixed->label())->toBe('Fixed Amount Discount')
        ->and(VoucherType::FreeShipping->label())->toBe('Free Shipping');
});

it('can get voucher type descriptions', function (): void {
    expect(VoucherType::Percentage->description())->toBe('Reduces cart total by a percentage')
        ->and(VoucherType::Fixed->description())->toBe('Reduces cart total by a fixed amount')
        ->and(VoucherType::FreeShipping->description())->toBe('Removes shipping costs');
});
