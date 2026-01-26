<?php

declare(strict_types=1);

use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Depleted;
use AIArmada\Vouchers\States\Expired;
use AIArmada\Vouchers\States\Paused;
use AIArmada\Vouchers\States\VoucherStatus;

it('can get voucher status labels', function (): void {
    expect(VoucherStatus::fromString(Active::class)->label())->toBe('Active')
        ->and(VoucherStatus::fromString(Paused::class)->label())->toBe('Paused')
        ->and(VoucherStatus::fromString(Expired::class)->label())->toBe('Expired')
        ->and(VoucherStatus::fromString(Depleted::class)->label())->toBe('Depleted');
});

it('can get voucher status descriptions', function (): void {
    expect(VoucherStatus::fromString(Active::class)->description())->toBe('Voucher can be used')
        ->and(VoucherStatus::fromString(Paused::class)->description())->toBe('Voucher temporarily disabled')
        ->and(VoucherStatus::fromString(Expired::class)->description())->toBe('Voucher past expiry date')
        ->and(VoucherStatus::fromString(Depleted::class)->description())->toBe('Voucher usage limit reached');
});

it('can check if voucher status can be used', function (): void {
    expect(VoucherStatus::fromString(Active::class)->canBeUsed())->toBeTrue()
        ->and(VoucherStatus::fromString(Paused::class)->canBeUsed())->toBeFalse()
        ->and(VoucherStatus::fromString(Expired::class)->canBeUsed())->toBeFalse()
        ->and(VoucherStatus::fromString(Depleted::class)->canBeUsed())->toBeFalse();
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
