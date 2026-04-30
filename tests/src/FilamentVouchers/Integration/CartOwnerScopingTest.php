<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentVouchers\Support\Integrations\FilamentCartBridge;
use AIArmada\Vouchers\Exceptions\VoucherException;
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
    // filament-vouchers enforces scoping via owner columns.
    config()->set('filament-cart.owner.enabled', false);

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
    Cart::query()->create([
        'identifier' => 'shared-identifier',
        'instance' => 'default',
        'currency' => 'USD',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
    ]);

    Cart::query()->create([
        'identifier' => 'shared-identifier',
        'instance' => 'default',
        'currency' => 'USD',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    Cart::query()->create([
        'identifier' => 'owner-b-only',
        'instance' => 'default',
        'currency' => 'USD',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

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

    $cartOwnedByB = Cart::query()->create([
        'identifier' => 'owner-b-direct-cart',
        'instance' => 'default',
        'currency' => 'USD',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

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

    app()->instance(\AIArmada\FilamentCart\Services\CartInstanceManager::class, $manager);

    expect($bridge->getCartInstance($cartOwnedByB))->toBeNull();
    expect($bridge->getAppliedVouchers($cartOwnedByB))->toBeEmpty();
    expect($bridge->hasVoucher($cartOwnedByB, 'ANY-CODE'))->toBeFalse();

    expect(fn () => $bridge->applyVoucher($cartOwnedByB, 'SAVE10'))
        ->toThrow(VoucherException::class, 'not authorized');

    expect(fn () => $bridge->removeVoucher($cartOwnedByB, 'SAVE10'))
        ->toThrow(VoucherException::class, 'not authorized');

    expect($manager->calls)->toBe(0);
});
