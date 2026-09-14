<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas\VoucherForm;
use AIArmada\Vouchers\Enums\VoucherType;

uses(TestCase::class);

it('caps percentage values at one hundred', function (): void {
    expect(VoucherForm::maxValueForType(VoucherType::Percentage->value))->toBe(100)
        ->and(VoucherForm::maxValueForType(VoucherType::Fixed->value))->toBeNull()
        ->and(VoucherForm::maxValueForType(null))->toBeNull();
});

it('preserves fractional upline values instead of truncating them', function (): void {
    expect(VoucherForm::normalizeUplineValue('2.5', 'percentage'))->toBe(2.5)
        ->and(VoucherForm::normalizeUplineValue(2, 'percentage'))->toBe(2)
        ->and(VoucherForm::normalizeUplineValue('50.00', 'fixed'))->toBe(5000)
        ->and(VoucherForm::normalizeUplineValue('', 'percentage'))->toBeNull();
});
