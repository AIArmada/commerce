<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Models\Voucher as FilamentVoucher;
use AIArmada\FilamentVouchers\Support\ConditionTargetPreset;
use AIArmada\FilamentVouchers\Support\Integrations\FilamentCartBridge;
use AIArmada\FilamentVouchers\Support\OwnerTypeRegistry;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

it('exposes condition target presets', function (): void {
    expect(ConditionTargetPreset::default())->toBe(ConditionTargetPreset::CartSubtotal);
    expect(ConditionTargetPreset::options())->toHaveKeys([
        'cart_subtotal',
        'grand_total',
        'shipments',
        'payments',
        'items',
        'custom',
    ]);

    expect(ConditionTargetPreset::detect(null))->toBeNull();
    expect(ConditionTargetPreset::detect(''))->toBeNull();
    expect(ConditionTargetPreset::detect('not-a-dsl'))->toBeNull();

    expect(ConditionTargetPreset::CartSubtotal->dsl())->toBeString();
    expect(ConditionTargetPreset::Custom->dsl())->toBeNull();
});

it('handles filament cart integration in both available and unavailable environments', function (): void {
    $bridge = new FilamentCartBridge;

    expect($bridge->resolveCartUrl(null))->toBeNull();
    expect($bridge->resolveCartUrl(''))->toBeNull();

    if ($bridge->isAvailable()) {
        expect($bridge->getCartModel())->toBeString();
        expect($bridge->getCartResource())->toBeString();

        $bridge->warm();

        return;
    }

    expect($bridge->getCartModel())->toBeNull();
    expect($bridge->getCartResource())->toBeNull();
});

it('resolves voucher owner display labels via OwnerTypeRegistry', function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('display_name')->nullable();
        $table->timestamps();
    });

    config()->set('filament-vouchers.owners', [
        [
            'model' => User::class,
            'label' => 'Users',
            'title_attribute' => 'name',
            'subtitle_attribute' => 'email',
            'search_attributes' => ['name', 'email'],
        ],
    ]);

    $registry = new OwnerTypeRegistry;

    expect($registry->hasDefinitions())->toBeTrue();
    expect($registry->options())->toHaveKey(User::class);

    $user = User::query()->create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'secret',
    ]);

    $results = $registry->search(User::class, 'Alice');
    expect($results)->toHaveKey($user->getKey());

    $label = $registry->resolveLabelForKey(User::class, $user->getKey());
    expect($label)->toContain('Alice');

    $voucher = FilamentVoucher::query()->create([
        'code' => 'TEST-OWNER-LABEL',
        'name' => 'Test Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $voucher->assignOwner($user)->save();

    expect($voucher->owner_display_name)->toContain('Alice');
});
