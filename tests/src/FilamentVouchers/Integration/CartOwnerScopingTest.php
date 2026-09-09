<?php

declare(strict_types=1);

use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\Cart\Snapshots\CartSnapshot as Cart;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Exceptions\VoucherException;
use AIArmada\Vouchers\Filament\Integrations\FilamentCartBridge;
use Illuminate\Database\Eloquent\Model;

uses(TestCase::class);

final class TestOwnerResolverForFilamentCartBridge implements OwnerResolverInterface
{
    public function __construct(private readonly ?Model $owner) {}

    public function resolve(): ?Model
    {
        return $this->owner;
    }
}

it('does not resolve cart urls across tenants when vouchers owner scoping is enabled', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);

    // Deliberately leave filament-cart owner scoping disabled to ensure
    // filament-vouchers enforces scoping via owner columns. Cart core
    // scoping stays enabled so snapshot rows carry real owners.
    config()->set('filament-cart.owner.enabled', false);
    config()->set('cart.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new TestOwnerResolverForFilamentCartBridge($ownerA));

    // Same identifier is allowed across owners because cart snapshots are unique on owner_key+identifier+instance.
    // Owner is assigned from context (owner columns are not mass-assignable by design).
    OwnerContext::withOwner($ownerA, fn (): mixed => Cart::query()->create([
        'identifier' => 'shared-identifier',
        'instance' => 'default',
        'currency' => 'USD',
    ]));

    OwnerContext::withOwner($ownerB, fn (): mixed => Cart::query()->create([
        'identifier' => 'shared-identifier',
        'instance' => 'default',
        'currency' => 'USD',
    ]));

    OwnerContext::withOwner($ownerB, fn (): mixed => Cart::query()->create([
        'identifier' => 'owner-b-only',
        'instance' => 'default',
        'currency' => 'USD',
    ]));

    $bridge = new FilamentCartBridge;

    // Owner A can resolve their own cart.
    if ($bridge->isAvailable()) {
        expect($bridge->resolveCartUrl('shared-identifier'))->toBeString();
    }

    // Owner A must not be able to resolve/link to Owner B's cart.
    expect($bridge->resolveCartUrl('owner-b-only'))->toBeNull();
});

it('blocks bridge cart operations across tenants even when a cart model is passed directly', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);
    config()->set('filament-cart.owner.enabled', false);
    config()->set('cart.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-bridge-guard@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-bridge-guard@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new TestOwnerResolverForFilamentCartBridge($ownerA));

    $cartOwnedByB = OwnerContext::withOwner($ownerB, fn (): mixed => Cart::query()->create([
        'identifier' => 'owner-b-direct-cart',
        'instance' => 'default',
        'currency' => 'USD',
    ]));

    $bridge = new FilamentCartBridge;

    if (! $bridge->isAvailable()) {
        test()->markTestSkipped('Filament cart integration is not available in this environment.');
    }

    $manager = new class
    {
        public int $calls = 0;

        public function resolve(string $instance, string $identifier): object
        {
            $this->calls++;

            return new class
            {
                public function getAppliedVouchers(): array
                {
                    return [];
                }

                public function applyVoucher(string $code): void {}

                public function removeVoucher(string $code): void {}
            };
        }
    };

    app()->instance(CartInstanceManager::class, $manager);

    expect($bridge->getCartInstance($cartOwnedByB))->toBeNull();
    expect($bridge->getAppliedVouchers($cartOwnedByB))->toBeEmpty();
    expect($bridge->hasVoucher($cartOwnedByB, 'ANY-CODE'))->toBeFalse();

    expect(fn () => $bridge->applyVoucher($cartOwnedByB, 'SAVE10'))
        ->toThrow(VoucherException::class, 'not authorized');

    expect(fn () => $bridge->removeVoucher($cartOwnedByB, 'SAVE10'))
        ->toThrow(VoucherException::class, 'not authorized');

    expect($manager->calls)->toBe(0);
});
