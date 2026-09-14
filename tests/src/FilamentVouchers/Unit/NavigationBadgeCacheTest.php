<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;

uses(TestCase::class);

it('caches navigation badges instead of counting on every render', function (): void {
    config()->set('vouchers.owner.enabled', false);

    Voucher::query()->create([
        'code' => 'BADGE-1',
        'name' => 'Badge',
        'type' => VoucherType::Fixed,
        'value' => 100,
        'currency' => 'MYR',
    ]);

    expect(VoucherResource::getNavigationBadge())->toBe('1')
        ->and(VoucherWalletResource::getNavigationBadge())->toBeNull();

    Voucher::query()->create([
        'code' => 'BADGE-2',
        'name' => 'Badge',
        'type' => VoucherType::Fixed,
        'value' => 100,
        'currency' => 'MYR',
    ]);

    // Still cached within the short badge TTL.
    expect(VoucherResource::getNavigationBadge())->toBe('1');
});
