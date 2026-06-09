<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;

uses(TestCase::class);

it('forces owner columns on create when owner mode enabled', function (): void {
    config()->set('vouchers.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-write@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $voucher = Voucher::query()->create([
        'code' => 'OWNER-FORCED-1',
        'name' => 'Owner Forced Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => Active::class,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    expect($voucher->owner_type)->toBe($ownerA->getMorphClass());
    expect((string) $voucher->owner_id)->toBe((string) $ownerA->getKey());
});

it('keeps global rows global on update when owner mode enabled', function (): void {
    config()->set('vouchers.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-update-global@example.com',
        'password' => 'secret',
    ]);

    $voucher = OwnerContext::withOwner(null, static fn (): Voucher => Voucher::query()->create([
        'code' => 'GLOBAL-UPDATE-1',
        'name' => 'Global Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => Active::class,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]));

    expect($voucher->owner_type)->toBeNull();
    expect($voucher->owner_id)->toBeNull();

    // Attempting to update owner fields on a global row should throw
    $voucher->owner_type = $ownerA->getMorphClass();
    $voucher->owner_id = (string) $ownerA->getKey();

    expect(fn () => $voucher->save())->toThrow(InvalidArgumentException::class);
});

it('prevents changing ownership on update when owner mode enabled', function (): void {
    config()->set('vouchers.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-update-owned@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-update-owned@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $voucher = Voucher::query()->create([
        'code' => 'OWNED-UPDATE-1',
        'name' => 'Owned Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => Active::class,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    expect($voucher->owner_type)->toBe($ownerA->getMorphClass());
    expect((string) $voucher->owner_id)->toBe((string) $ownerA->getKey());

    // Attempting to change the owner should preserve original and throw
    $voucher->refresh();
    $voucher->owner_type = $ownerB->getMorphClass();
    $voucher->owner_id = (string) $ownerB->getKey();

    expect(fn () => $voucher->save())->toThrow(InvalidArgumentException::class);
});
