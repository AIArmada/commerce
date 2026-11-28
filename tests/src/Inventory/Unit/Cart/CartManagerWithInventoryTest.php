<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Cart\CartManagerWithInventory;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;

final class FakeInventoryCartManager implements CartManagerInterface
{
    public function __construct(private Cart $cart) {}

    public function getCurrentCart(): Cart
    {
        return $this->cart;
    }

    public function getCartInstance(string $name, ?string $identifier = null): Cart
    {
        return $this->cart;
    }

    public function instance(): string
    {
        return 'default';
    }

    public function setInstance(string $name): static
    {
        return $this;
    }

    public function setIdentifier(string $identifier): static
    {
        return $this;
    }

    public function forgetIdentifier(): static
    {
        return $this;
    }

    public function forOwner(Model $owner): static
    {
        return $this;
    }

    public function getOwnerType(): ?string
    {
        return null;
    }

    public function getOwnerId(): string | int | null
    {
        return null;
    }

    public function getById(string $uuid): ?Cart
    {
        return null;
    }

    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        return false;
    }
}

describe('CartManagerWithInventory batch atomicity', function (): void {
    beforeEach(function (): void {
        $this->inventoryService = app(InventoryService::class);

        $this->location = InventoryLocation::factory()->create([
            'name' => 'Atomic WH',
            'code' => 'ATOMIC-WH',
            'priority' => 10,
        ]);

        $this->itemA = InventoryItem::create(['name' => 'Atomic Item A']);
        $this->itemB = InventoryItem::create(['name' => 'Atomic Item B']);

        $this->inventoryService->receive($this->itemA, $this->location->id, 10);
        $this->inventoryService->receive($this->itemB, $this->location->id, 1);
    });

    it('rolls back earlier allocations when a later item runs out of stock', function (): void {
        $cart = new Cart(new InMemoryStorage, 'atomic-batch-cart');
        $cart->add(id: 'item-a', name: 'Item A', price: 100, quantity: 5, associatedModel: $this->itemA);
        $cart->add(id: 'item-b', name: 'Item B', price: 100, quantity: 5, associatedModel: $this->itemB);

        $manager = CartManagerWithInventory::fromCartManager(new FakeInventoryCartManager($cart));

        expect(fn (): array => $manager->allocateAllInventory())->toThrow(InsufficientInventoryException::class);

        expect(InventoryAllocation::query()->count())->toBe(0);

        $levelA = $this->inventoryService->getLevel($this->itemA, $this->location->id)?->fresh();
        $levelB = $this->inventoryService->getLevel($this->itemB, $this->location->id)?->fresh();

        expect($levelA?->quantity_reserved)->toBe(0)
            ->and($levelB?->quantity_reserved)->toBe(0);
    });

    it('allocates all items when stock is sufficient', function (): void {
        $this->inventoryService->receive($this->itemB, $this->location->id, 10);

        $cart = new Cart(new InMemoryStorage, 'atomic-success-cart');
        $cart->add(id: 'item-a', name: 'Item A', price: 100, quantity: 5, associatedModel: $this->itemA);
        $cart->add(id: 'item-b', name: 'Item B', price: 100, quantity: 5, associatedModel: $this->itemB);

        $manager = CartManagerWithInventory::fromCartManager(new FakeInventoryCartManager($cart));

        $results = $manager->allocateAllInventory();

        expect($results)->toHaveCount(2)
            ->and(InventoryAllocation::query()->count())->toBeGreaterThan(0);
    });
});

it('validates and reserves the explicitly supplied cart instead of the active cart', function (): void {
    $service = app(InventoryService::class);
    $location = InventoryLocation::factory()->create([
        'name' => 'Explicit checkout warehouse',
        'code' => 'EXPLICIT-CHECKOUT-WH',
    ]);
    $activeItem = InventoryItem::create(['name' => 'Active cart item']);
    $checkoutItem = InventoryItem::create(['name' => 'Checkout cart item']);
    $service->receive($activeItem, $location->id, 1);
    $service->receive($checkoutItem, $location->id, 10);

    $activeCart = new Cart(new InMemoryStorage, 'active-cart');
    $activeCart->add(id: 'active-item', name: 'Active item', price: 100, quantity: 5, associatedModel: $activeItem);
    $checkoutCart = new Cart(new InMemoryStorage, 'checkout-cart');
    $checkoutCart->add(id: 'checkout-item', name: 'Checkout item', price: 100, quantity: 3, associatedModel: $checkoutItem);

    $manager = CartManagerWithInventory::fromCartManager(new FakeInventoryCartManager($activeCart));

    expect($manager->validateInventory()['available'])->toBeFalse()
        ->and($manager->validateInventory($checkoutCart)['available'])->toBeTrue();

    $manager->allocateAllInventory(30, $checkoutCart);

    expect($service->getLevel($activeItem, $location->id)?->fresh()->quantity_reserved)->toBe(0)
        ->and($service->getLevel($checkoutItem, $location->id)?->fresh()->quantity_reserved)->toBe(3)
        ->and($manager->getCurrentCart())->toBe($activeCart);
});
