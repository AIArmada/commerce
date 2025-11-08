<?php

declare(strict_types=1);

use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\Traits\HasVoucherWallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Config::set('vouchers.wallet.enabled', true);

    // Create users table for testing
    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });
    }

    // Create test model class that uses HasVoucherWallet
    if (! class_exists(TestWalletUser::class)) {
        eval('
            class TestWalletUser extends Illuminate\Database\Eloquent\Model
            {
                use AIArmada\Vouchers\Traits\HasVoucherWallet;
                protected $table = "users";
                protected $guarded = [];
            }
        ');
    }
});

test('can add voucher to wallet using trait', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    $voucher = Voucher::create([
        'code' => 'WALLET10',
        'name' => 'Wallet Test Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $walletEntry = $user->addVoucherToWallet('WALLET10');

    expect($walletEntry)->toBeInstanceOf(VoucherWallet::class)
        ->and($walletEntry->voucher_id)->toBe($voucher->id)
        ->and($walletEntry->owner_id)->toBe($user->id)
        ->and($walletEntry->owner_type)->toBe($user->getMorphClass())
        ->and($walletEntry->is_claimed)->toBeTrue()
        ->and($walletEntry->claimed_at)->not->toBeNull();
});

test('can check if voucher exists in wallet', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'EXISTCHECK',
        'name' => 'Exist Check Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    expect($user->hasVoucherInWallet('EXISTCHECK'))->toBeFalse();

    $user->addVoucherToWallet('EXISTCHECK');

    expect($user->hasVoucherInWallet('EXISTCHECK'))->toBeTrue();
});

test('can get available vouchers from wallet', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'AVAILABLE1',
        'name' => 'Available Voucher 1',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    Voucher::create([
        'code' => 'AVAILABLE2',
        'name' => 'Available Voucher 2',
        'type' => 'fixed',
        'value' => 500,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $user->addVoucherToWallet('AVAILABLE1');
    $user->addVoucherToWallet('AVAILABLE2');

    $availableVouchers = $user->getAvailableVouchers();

    expect($availableVouchers)->toHaveCount(2);
});

test('can get redeemed vouchers from wallet', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'REDEEMED1',
        'name' => 'Redeemed Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $walletEntry = $user->addVoucherToWallet('REDEEMED1');
    $walletEntry->markAsRedeemed();

    $redeemedVouchers = $user->getRedeemedVouchers();

    expect($redeemedVouchers)->toHaveCount(1)
        ->and($redeemedVouchers->first()->is_redeemed)->toBeTrue();
});

test('can get expired vouchers from wallet', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'EXPIRED1',
        'name' => 'Expired Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
        'expires_at' => now()->subDay(),
    ]);

    $user->addVoucherToWallet('EXPIRED1');

    $expiredVouchers = $user->getExpiredVouchers();

    expect($expiredVouchers)->toHaveCount(1);
});

test('can mark voucher as redeemed in wallet', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'MARKREDEEM',
        'name' => 'Mark Redeem Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $walletEntry = $user->addVoucherToWallet('MARKREDEEM');

    expect($walletEntry->is_redeemed)->toBeFalse();

    $user->markVoucherAsRedeemed('MARKREDEEM');

    expect($walletEntry->fresh()->is_redeemed)->toBeTrue()
        ->and($walletEntry->fresh()->redeemed_at)->not->toBeNull();
});

test('can remove voucher from wallet', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'REMOVE1',
        'name' => 'Remove Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $user->addVoucherToWallet('REMOVE1');
    expect($user->hasVoucherInWallet('REMOVE1'))->toBeTrue();

    $removed = $user->removeVoucherFromWallet('REMOVE1');

    expect($removed)->toBeTrue()
        ->and($user->hasVoucherInWallet('REMOVE1'))->toBeFalse();
});

test('cannot remove redeemed voucher from wallet', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'CANTREMOVE',
        'name' => 'Cant Remove Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $walletEntry = $user->addVoucherToWallet('CANTREMOVE');
    $walletEntry->markAsRedeemed();

    $removed = $user->removeVoucherFromWallet('CANTREMOVE');

    expect($removed)->toBeFalse()
        ->and($user->hasVoucherInWallet('CANTREMOVE'))->toBeTrue();
});

test('voucher service can add to wallet', function (): void {
    $service = app(VoucherService::class);
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'SERVICE1',
        'name' => 'Service Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $walletEntry = $service->addToWallet('SERVICE1', $user, ['source' => 'test']);

    expect($walletEntry)->toBeInstanceOf(VoucherWallet::class)
        ->and($walletEntry->metadata)->toHaveKey('source')
        ->and($walletEntry->metadata['source'])->toBe('test');
});

test('voucher service can remove from wallet', function (): void {
    $service = app(VoucherService::class);
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'SERVICE2',
        'name' => 'Service Remove Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service->addToWallet('SERVICE2', $user);
    $removed = $service->removeFromWallet('SERVICE2', $user);

    expect($removed)->toBeTrue();
});

test('wallet entry knows if voucher is available', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'AVAILABLE',
        'name' => 'Available Check',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $walletEntry = $user->addVoucherToWallet('AVAILABLE');

    expect($walletEntry->isAvailable())->toBeTrue();

    $walletEntry->markAsRedeemed();

    expect($walletEntry->isAvailable())->toBeFalse();
});

test('wallet entry knows if voucher can be used', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'CANUSE',
        'name' => 'Can Use Check',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);

    $walletEntry = $user->addVoucherToWallet('CANUSE');

    expect($walletEntry->canBeUsed())->toBeTrue();
});

test('wallet entry knows if voucher is expired', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    Voucher::create([
        'code' => 'EXPIRED',
        'name' => 'Expired Check',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
        'expires_at' => now()->subDay(),
    ]);

    $walletEntry = $user->addVoucherToWallet('EXPIRED');

    expect($walletEntry->isExpired())->toBeTrue();
});
