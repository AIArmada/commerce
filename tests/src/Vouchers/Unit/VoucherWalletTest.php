<?php

declare(strict_types=1);

use AIArmada\Vouchers\Features\Wallet\Api\WalletEntryDeletion;
use AIArmada\Vouchers\Features\Wallet\Api\WalletTimestampTransition;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\Traits\HasVouchers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Config::set('vouchers.wallet.enabled', true);

    // Keep the persisted tenant user separate from the wallet holder fixture.
    Schema::dropIfExists('test_wallet_users');
    Schema::create('test_wallet_users', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('password')->nullable();
        $table->timestamps();
    });

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
        ->and($walletEntry->holder_id)->toBe($user->id)
        ->and($walletEntry->holder_type)->toBe($user->getMorphClass())
        ->and($walletEntry->claimed_at)->not->toBeNull();
});

test('rejects wallet writes for an unpersisted holder', function (): void {
    $user = new TestWalletUser(['name' => 'Unsaved', 'email' => 'unsaved@example.com']);

    Voucher::create([
        'code' => 'UNSAVED-HOLDER',
        'name' => 'Unsaved Holder Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    expect(fn () => $user->addVoucherToWallet('UNSAVED-HOLDER'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $user->removeVoucherFromWallet('UNSAVED-HOLDER'))->toThrow(InvalidArgumentException::class)
        ->and(VoucherWallet::query()->count())->toBe(0);
});

test('wallet mutation APIs deduplicate identifiers and keep bulk deletion atomic', function (): void {
    config(['vouchers.owner.enabled' => false]);

    $user = TestWalletUser::create(['name' => 'Bulk User', 'email' => 'bulk@example.com']);

    $voucher = Voucher::create([
        'code' => 'BULK-WALLET',
        'name' => 'Bulk Wallet',
        'type' => 'fixed',
        'value' => 100,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $wallet = $user->addVoucherToWallet($voucher->code);
    $id = (string) $wallet->getKey();

    app(WalletTimestampTransition::class)->handleMany([$id, $id], true);

    expect($wallet->fresh()->redeemed_at)->not->toBeNull();

    expect(fn () => app(WalletEntryDeletion::class)->handleMany([$id, 'ffffffff-ffff-ffff-ffff-ffffffffffff']))
        ->toThrow(RuntimeException::class);

    expect($wallet->fresh())->not->toBeNull();

    app(WalletEntryDeletion::class)->handleMany([$id, $id]);

    expect($wallet->fresh())->toBeNull();
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

test('can claim voucher wallet entry', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    $voucher = Voucher::create([
        'code' => 'CLAIMTEST',
        'name' => 'Claim Test Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $walletEntry = VoucherWallet::create([
        'voucher_id' => $voucher->id,
        'holder_id' => $user->id,
        'holder_type' => $user->getMorphClass(),
    ]);

    expect($walletEntry->claimed_at)->toBeNull();

    $walletEntry->claim();

    $walletEntry->refresh();

    expect($walletEntry->claimed_at)->not->toBeNull();

    // Claiming again should not change
    $originalClaimedAt = $walletEntry->claimed_at;
    $walletEntry->claim();
    expect($walletEntry->claimed_at)->toEqual($originalClaimedAt);
});

test('can mark voucher wallet entry as redeemed', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    $voucher = Voucher::create([
        'code' => 'REDEEMTEST',
        'name' => 'Redeem Test Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $walletEntry = VoucherWallet::create([
        'voucher_id' => $voucher->id,
        'holder_id' => $user->id,
        'holder_type' => $user->getMorphClass(),
        'claimed_at' => now(),
    ]);

    expect($walletEntry->redeemed_at)->toBeNull();

    $walletEntry->markAsRedeemed();

    $walletEntry->refresh();

    expect($walletEntry->redeemed_at)->not->toBeNull();

    // Marking again should not change
    $originalRedeemedAt = $walletEntry->redeemed_at;
    $walletEntry->markAsRedeemed();
    expect($walletEntry->redeemed_at)->toEqual($originalRedeemedAt);
});

test('can check if voucher wallet entry is expired', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    $expiredVoucher = Voucher::create([
        'code' => 'EXPIRED',
        'name' => 'Expired Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
        'expires_at' => now()->subDay(),
    ]);

    $activeVoucher = Voucher::create([
        'code' => 'ACTIVE',
        'name' => 'Active Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $expiredWallet = VoucherWallet::create([
        'voucher_id' => $expiredVoucher->id,
        'holder_id' => $user->id,
        'holder_type' => $user->getMorphClass(),
        'claimed_at' => now(),
    ]);

    $activeWallet = VoucherWallet::create([
        'voucher_id' => $activeVoucher->id,
        'holder_id' => $user->id,
        'holder_type' => $user->getMorphClass(),
        'claimed_at' => now(),
    ]);

    expect($expiredWallet->isExpired())->toBeTrue()
        ->and($activeWallet->isExpired())->toBeFalse();
});

test('can check if voucher wallet entry can be used', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    $expiredVoucher = Voucher::create([
        'code' => 'EXPIRED',
        'name' => 'Expired Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
        'expires_at' => now()->subDay(),
    ]);

    $activeVoucher = Voucher::create([
        'code' => 'ACTIVE',
        'name' => 'Active Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $inactiveVoucher = Voucher::create([
        'code' => 'INACTIVE',
        'name' => 'Inactive Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'paused',
    ]);

    $notStartedVoucher = Voucher::create([
        'code' => 'NOTSTARTED',
        'name' => 'Not Started Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
        'starts_at' => now()->addDay(),
    ]);

    $expiredWallet = VoucherWallet::create([
        'voucher_id' => $expiredVoucher->id,
        'holder_id' => $user->id,
        'holder_type' => $user->getMorphClass(),
        'claimed_at' => now(),
    ]);

    $activeWallet = VoucherWallet::create([
        'voucher_id' => $activeVoucher->id,
        'holder_id' => $user->id,
        'holder_type' => $user->getMorphClass(),
        'claimed_at' => now(),
    ]);

    $unclaimedWallet = VoucherWallet::create([
        'voucher_id' => $notStartedVoucher->id,
        'holder_id' => $user->id,
        'holder_type' => $user->getMorphClass(),
    ]);

    $inactiveWallet = VoucherWallet::create([
        'voucher_id' => $inactiveVoucher->id,
        'holder_id' => $user->id,
        'holder_type' => $user->getMorphClass(),
        'claimed_at' => now(),
    ]);

    expect($expiredWallet->canBeUsed())->toBeFalse()
        ->and($activeWallet->canBeUsed())->toBeTrue()
        ->and($unclaimedWallet->canBeUsed())->toBeFalse()
        ->and($inactiveWallet->canBeUsed())->toBeFalse();
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

test('exhausted vouchers cannot be used and do not consume the available wallet limit', function (): void {
    $user = TestWalletUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

    $exhausted = Voucher::create([
        'code' => 'EXHAUSTED',
        'name' => 'Exhausted Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit' => 1,
    ]);

    $wallet = $user->addVoucherToWallet('EXHAUSTED');

    VoucherUsage::create([
        'voucher_id' => $exhausted->id,
        'discount_amount' => 100,
        'currency' => 'MYR',
        'channel' => 'web',
        'used_at' => now(),
    ]);

    Voucher::create([
        'code' => 'STILL-USABLE',
        'name' => 'Still Usable Voucher',
        'type' => 'percentage',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $available = $user->addVoucherToWallet('STILL-USABLE');

    expect($wallet->fresh()->canBeUsed())->toBeFalse()
        ->and($user->getAvailableVouchers(1)->pluck('id')->all())->toBe([$available->id]);
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
        ->and($redeemedVouchers->first()->redeemed_at)->not->toBeNull();
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

    expect($walletEntry->redeemed_at)->toBeNull();

    $user->markVoucherAsRedeemed('MARKREDEEM');

    expect($walletEntry->fresh()->redeemed_at)->not->toBeNull();
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

final class TestWalletUser extends Model
{
    use HasVouchers;

    protected $table = 'test_wallet_users';

    protected $guarded = [];
}
