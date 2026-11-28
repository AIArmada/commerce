<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentVouchers\Actions\AddToMyWalletAction;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Features\Lifecycle\Models\Voucher;
use AIArmada\Vouchers\Features\Lifecycle\Models\VoucherWallet;

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

it('claims the selected global voucher rather than a tenant voucher with the same code', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Wallet Collision Owner',
        'email' => 'wallet-collision-owner@example.com',
        'password' => 'secret',
    ]);

    $attributes = [
        'code' => 'SHARED-WALLET-CODE',
        'name' => 'Shared Wallet Code',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => 'active',
    ];

    $global = OwnerContext::withOwner(null, static fn (): Voucher => Voucher::query()->create($attributes));
    $owned = OwnerContext::withOwner($owner, static fn (): Voucher => Voucher::query()->create($attributes));

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($owner));
    test()->actingAs($owner);

    $handler = AddToMyWalletAction::make()->record($global)->getActionFunction();

    OwnerContext::withOwner($owner, static function () use ($handler, $global, $owned): void {
        $handler(['notes' => 'Selected global voucher'], $global);

        expect(VoucherWallet::query()->where('voucher_id', $global->getKey())->sole()->metadata)
            ->toBe(['notes' => 'Selected global voucher'])
            ->and(VoucherWallet::query()->where('voucher_id', $owned->getKey())->exists())->toBeFalse();
    });
});
