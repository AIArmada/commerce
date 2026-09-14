<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature\Integrations;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Orders\Models\Order;
use AIArmada\Shipping\Integrations\OrderFulfillmentHandler;
use Illuminate\Support\Str;
use ReflectionMethod;

function fulfillmentLocationOwner(string $label): User
{
    return User::query()->create([
        'name' => $label,
        'email' => Str::lower(Str::random(10)) . '@example.com',
        'password' => 'secret',
    ]);
}

function fulfillmentLocation(User $owner, string $code): InventoryLocation
{
    return OwnerContext::withOwner($owner, fn (): InventoryLocation => InventoryLocation::query()->create([
        'name' => 'Warehouse ' . $code,
        'code' => $code,
        'line1' => '1 Warehouse Road',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country' => 'MY',
    ]));
}

function fulfillmentOrderFor(User $owner): Order
{
    $order = new Order;
    $order->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    return $order;
}

function resolveFulfillmentLocation(string $locationId, Order $order): ?object
{
    $method = new ReflectionMethod(OrderFulfillmentHandler::class, 'getLocationById');
    $method->setAccessible(true);

    return $method->invoke(app(OrderFulfillmentHandler::class), $locationId, $order);
}

describe('Forced fulfillment location scoping', function (): void {
    beforeEach(function (): void {
        config()->set('inventory.owner.enabled', true);
        config()->set('inventory.owner.include_global', false);
    });

    it('rejects a forced location owned by another owner', function (): void {
        $ownerA = fulfillmentLocationOwner('Fulfillment Owner A');
        $ownerB = fulfillmentLocationOwner('Fulfillment Owner B');
        $foreignLocation = fulfillmentLocation($ownerB, 'WH-' . Str::upper(Str::random(6)));

        $resolved = OwnerContext::withOwner(
            $ownerA,
            fn (): ?object => resolveFulfillmentLocation((string) $foreignLocation->getKey(), fulfillmentOrderFor($ownerA))
        );

        expect($resolved)->toBeNull();
    });

    it('accepts a forced location owned by the order owner', function (): void {
        $owner = fulfillmentLocationOwner('Fulfillment Owner');
        $ownLocation = fulfillmentLocation($owner, 'WH-' . Str::upper(Str::random(6)));

        $resolved = OwnerContext::withOwner(
            $owner,
            fn (): ?object => resolveFulfillmentLocation((string) $ownLocation->getKey(), fulfillmentOrderFor($owner))
        );

        expect($resolved)->not->toBeNull()
            ->and((string) $resolved->getKey())->toBe((string) $ownLocation->getKey());
    });
});
