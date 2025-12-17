<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Models\Voucher as FilamentVoucher;
use AIArmada\FilamentVouchers\Support\OwnerTypeRegistry;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;

uses(TestCase::class);

it('computes owner_display_name attribute on filament voucher model', function (): void {
    config()->set('filament-vouchers.owners', [
        [
            'model' => User::class,
            'label' => 'Users',
            'title_attribute' => 'name',
        ],
    ]);

    $registry = new OwnerTypeRegistry;
    expect($registry->hasDefinitions())->toBeTrue();

    $owner = User::query()->create([
        'name' => 'Voucher Owner',
        'email' => 'voucher-owner@example.com',
        'password' => 'secret',
    ]);

    $globalVoucher = FilamentVoucher::query()->create([
        'code' => 'MODEL-0',
        'name' => 'Global Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    expect($globalVoucher->owner_display_name)->toBeNull();

    $voucher = FilamentVoucher::query()->create([
        'code' => 'MODEL-1',
        'name' => 'Model Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $voucher->assignOwner($owner)->save();

    expect($voucher->owner_display_name)->toContain('Voucher Owner');
});
