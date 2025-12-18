<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentVouchers\Support\Integrations\FilamentCartBridge;
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
