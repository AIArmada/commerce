<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentVouchers\Actions\AddToMyWalletAction;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherWallet;

uses(TestCase::class);

it('claims the exact voucher with notes through the wallet action', function (): void {
    $user = User::query()->create([
        'name' => 'Wallet Action User',
        'email' => 'wallet-action-user@example.com',
        'password' => 'secret',
    ]);

    test()->actingAs($user);

    $voucher = Voucher::query()->create([
        'code' => 'WALLET-ACTION-CODE',
        'name' => 'Wallet Action Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => 'active',
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $action = AddToMyWalletAction::make();
    $handler = $action->record($voucher)->getActionFunction();

    expect($handler)->not->toBeNull();

    $handler([
        'notes' => 'Remember for checkout',
    ], $voucher);

    $entry = VoucherWallet::query()->where('voucher_id', $voucher->getKey())->sole();

    expect((string) $entry->holder_id)->toBe((string) $user->getKey())
        ->and($entry->holder_type)->toBe($user->getMorphClass())
        ->and($entry->metadata)->toBe(['notes' => 'Remember for checkout']);

    $handler(['notes' => 'Do not overwrite'], $voucher);

    expect(VoucherWallet::query()->where('voucher_id', $voucher->getKey())->count())->toBe(1)
        ->and($entry->fresh()->metadata)->toBe(['notes' => 'Remember for checkout']);
});

it('does not add cross-owner voucher to wallet when owner scoping is enabled', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);

    $user = User::query()->create([
        'name' => 'Scoped Wallet User',
        'email' => 'scoped-wallet-user@example.com',
        'password' => 'secret',
    ]);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'wallet-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'wallet-owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $voucherOwnedByB = OwnerContext::withOwner($ownerB, static fn (): Voucher => Voucher::query()->create([
        'code' => 'WALLET-OTHER-OWNER',
        'name' => 'Other Owner Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => 'active',
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]));

    test()->actingAs($user);

    $action = AddToMyWalletAction::make();
    $handler = $action->record($voucherOwnedByB)->getActionFunction();

    expect($handler)->not->toBeNull();

    $handler([
        'notes' => 'Should be blocked',
    ], $voucherOwnedByB);

    expect(VoucherWallet::withoutGlobalScopes()->where('voucher_id', $voucherOwnedByB->getKey())->exists())->toBeFalse();
});
