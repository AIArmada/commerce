<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Contracts\CartValidationResult;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Cart\CartManagerWithInventory;
use AIArmada\Inventory\Cart\InventoryValidator;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Listeners\ReserveStockOnCheckout;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use Illuminate\Database\Eloquent\Model;

final class CompositeFakeCartManager implements CartManagerInterface
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

/**
 * @param  list<array{product: InventoryItem, quantity: int}>  $components
 * @return list<array<string, mixed>>
 */
function compositeComponentSnapshot(array $components): array
{
    return array_map(static fn (array $component): array => [
        'product_id' => $component['product']->getKey(),
        'variant_id' => null,
        'inventoryable_type' => $component['product']->getMorphClass(),
        'inventoryable_id' => $component['product']->getKey(),
        'quantity' => $component['quantity'],
        'tracks_inventory' => true,
        'requires_shipping' => true,
        'name' => $component['product']->name,
        'sku' => 'SKU-' . $component['product']->getKey(),
        'weight' => 100,
        'dimensions' => ['length' => 10, 'width' => 10, 'height' => 10],
    ], $components);
}

beforeEach(function (): void {
    $this->inventoryService = app(InventoryService::class);
    $this->allocationService = app(InventoryAllocationService::class);
    $this->location = InventoryLocation::factory()->create([
        'name' => 'Composite WH',
        'code' => 'COMPOSITE-WH-' . uniqid(),
        'priority' => 100,
    ]);
});

describe('composite cart inventory demand', function (): void {
    it('allocates component quantities times packages and merges standalone demand', function (): void {
        $mouse = InventoryItem::create(['name' => 'Mouse']);
        $cable = InventoryItem::create(['name' => 'Cable']);
        $parent = InventoryItem::create(['name' => 'Mouse Bundle']);

        $this->inventoryService->receive($mouse, $this->location->id, 5);
        $this->inventoryService->receive($cable, $this->location->id, 3);
        $this->inventoryService->receive($parent, $this->location->id, 50);

        $cart = new Cart(new InMemoryStorage, 'composite-demand-cart');
        $cart->add(
            id: 'mouse-bundle',
            name: 'Mouse Bundle',
            price: 2000,
            quantity: 2,
            attributes: [
                'components' => compositeComponentSnapshot([
                    ['product' => $mouse, 'quantity' => 2],
                    ['product' => $cable, 'quantity' => 1],
                ]),
            ],
            associatedModel: $parent,
        );
        $cart->add(id: 'standalone-mouse', name: 'Mouse', price: 500, quantity: 1, associatedModel: $mouse);

        $manager = CartManagerWithInventory::fromCartManager(new CompositeFakeCartManager($cart));

        $results = $manager->allocateAllInventory();

        expect($results)->toHaveCount(2)
            ->and(InventoryAllocation::query()
                ->where('inventoryable_type', $mouse->getMorphClass())
                ->where('inventoryable_id', $mouse->getKey())
                ->sum('quantity'))->toBe(5)
            ->and(InventoryAllocation::query()
                ->where('inventoryable_type', $cable->getMorphClass())
                ->where('inventoryable_id', $cable->getKey())
                ->sum('quantity'))->toBe(2)
            ->and(InventoryAllocation::query()
                ->where('inventoryable_type', $parent->getMorphClass())
                ->where('inventoryable_id', $parent->getKey())
                ->count())->toBe(0)
            ->and($this->inventoryService->getLevel($parent, $this->location->id)?->fresh()->quantity_reserved)->toBe(0);
    });

    it('rolls back every component allocation when aggregated demand is short', function (): void {
        $mouse = InventoryItem::create(['name' => 'Mouse']);
        $cable = InventoryItem::create(['name' => 'Cable']);
        $parent = InventoryItem::create(['name' => 'Mouse Bundle']);

        $this->inventoryService->receive($mouse, $this->location->id, 4);
        $this->inventoryService->receive($cable, $this->location->id, 5);
        $this->inventoryService->receive($parent, $this->location->id, 50);

        $cart = new Cart(new InMemoryStorage, 'composite-short-cart');
        $cart->add(
            id: 'mouse-bundle',
            name: 'Mouse Bundle',
            price: 2000,
            quantity: 2,
            attributes: [
                'components' => compositeComponentSnapshot([
                    ['product' => $mouse, 'quantity' => 2],
                    ['product' => $cable, 'quantity' => 1],
                ]),
            ],
            associatedModel: $parent,
        );
        $cart->add(id: 'standalone-mouse', name: 'Mouse', price: 500, quantity: 1, associatedModel: $mouse);

        $manager = CartManagerWithInventory::fromCartManager(new CompositeFakeCartManager($cart));

        expect($manager->validateInventory()['available'])->toBeFalse()
            ->and(fn (): array => $manager->allocateAllInventory())->toThrow(InsufficientInventoryException::class)
            ->and(InventoryAllocation::query()->count())->toBe(0);
    });

    it('never allocates a composite parent with an empty component snapshot', function (): void {
        $parent = InventoryItem::create(['name' => 'Empty Bundle']);
        $this->inventoryService->receive($parent, $this->location->id, 10);

        $cart = new Cart(new InMemoryStorage, 'empty-composite-cart');
        $cart->add(
            id: 'empty-bundle',
            name: 'Empty Bundle',
            price: 1000,
            quantity: 3,
            attributes: ['components' => []],
            associatedModel: $parent,
        );

        $manager = CartManagerWithInventory::fromCartManager(new CompositeFakeCartManager($cart));

        expect($manager->allocateAllInventory())->toBe([])
            ->and(InventoryAllocation::query()->count())->toBe(0)
            ->and($this->inventoryService->getLevel($parent, $this->location->id)?->fresh()->quantity_reserved)->toBe(0);
    });

    it('validates the combined component and standalone demand', function (): void {
        $mouse = InventoryItem::create(['name' => 'Mouse']);
        $parent = InventoryItem::create(['name' => 'Mouse Pair']);

        $this->inventoryService->receive($mouse, $this->location->id, 5);
        $this->inventoryService->receive($parent, $this->location->id, 10);

        $cart = new Cart(new InMemoryStorage, 'composite-validator-cart');
        $cart->add(
            id: 'mouse-pair',
            name: 'Mouse Pair',
            price: 1000,
            quantity: 2,
            attributes: [
                'components' => compositeComponentSnapshot([
                    ['product' => $mouse, 'quantity' => 2],
                ]),
            ],
            associatedModel: $parent,
        );
        $cart->add(id: 'standalone-mouse', name: 'Mouse', price: 500, quantity: 1, associatedModel: $mouse);

        $validator = new InventoryValidator($this->allocationService);

        expect($validator->validateCart($cart))->toBeInstanceOf(CartValidationResult::class)
            ->and($validator->validateCart($cart)->isValid)->toBeTrue();

        $this->inventoryService->getLevel($mouse, $this->location->id)?->update(['quantity_on_hand' => 4]);

        $result = $validator->validateCart($cart);

        expect($result->isValid)->toBeFalse()
            ->and($result->errors)->toHaveKeys(['mouse-pair', 'standalone-mouse'])
            ->and($result->metadata['items']['mouse-pair']['requested_quantity'])->toBe(5);
    });
});

describe('composite add and checkout reservation listeners', function (): void {
    it('blocks an add when the combined cart demand for a component is short', function (): void {
        config()->set('inventory.cart.validate_on_add', true);
        config()->set('inventory.cart.auto_allocate_on_add', false);
        config()->set('inventory.cart.allow_backorder', false);

        $mouse = InventoryItem::create(['name' => 'Add Mouse']);
        $parent = InventoryItem::create(['name' => 'Add Bundle']);

        $this->inventoryService->receive($mouse, $this->location->id, 4);
        $this->inventoryService->receive($parent, $this->location->id, 10);

        $cart = new Cart(new InMemoryStorage, 'composite-add-short-cart', app('events'));
        $cart->add(
            id: 'mouse-pair',
            name: 'Mouse Pair',
            price: 1000,
            quantity: 2,
            attributes: [
                'components' => compositeComponentSnapshot([
                    ['product' => $mouse, 'quantity' => 2],
                ]),
            ],
            associatedModel: $parent,
        );

        expect(fn () => $cart->add(
            id: 'standalone-mouse',
            name: 'Mouse',
            price: 500,
            quantity: 1,
            associatedModel: $mouse,
        ))->toThrow(InsufficientInventoryException::class);

        expect(InventoryAllocation::query()->count())->toBe(0);
    });

    it('auto-allocates the merged component demand without allocating the parent', function (): void {
        config()->set('inventory.cart.validate_on_add', true);
        config()->set('inventory.cart.auto_allocate_on_add', true);
        config()->set('inventory.cart.allow_backorder', false);

        $mouse = InventoryItem::create(['name' => 'Auto Mouse']);
        $parent = InventoryItem::create(['name' => 'Auto Bundle']);

        $this->inventoryService->receive($mouse, $this->location->id, 5);
        $this->inventoryService->receive($parent, $this->location->id, 10);

        $cart = new Cart(new InMemoryStorage, 'composite-add-auto-cart', app('events'));
        $cart->add(
            id: 'mouse-pair',
            name: 'Mouse Pair',
            price: 1000,
            quantity: 2,
            attributes: [
                'components' => compositeComponentSnapshot([
                    ['product' => $mouse, 'quantity' => 2],
                ]),
            ],
            associatedModel: $parent,
        );
        $cart->add(id: 'standalone-mouse', name: 'Mouse', price: 500, quantity: 1, associatedModel: $mouse);

        expect(InventoryAllocation::query()
            ->where('inventoryable_type', $mouse->getMorphClass())
            ->where('inventoryable_id', $mouse->getKey())
            ->sum('quantity'))->toBe(5)
            ->and(InventoryAllocation::query()
                ->where('inventoryable_type', $parent->getMorphClass())
                ->where('inventoryable_id', $parent->getKey())
                ->count())->toBe(0);
    });

    it('reserves merged component demand on checkout', function (): void {
        config()->set('inventory.cart.reserve_on_checkout', true);
        config()->set('inventory.cart.validate_on_add', false);

        $mouse = InventoryItem::create(['name' => 'Checkout Mouse']);
        $parent = InventoryItem::create(['name' => 'Checkout Bundle']);

        $this->inventoryService->receive($mouse, $this->location->id, 5);
        $this->inventoryService->receive($parent, $this->location->id, 10);

        $cart = new Cart(new InMemoryStorage, 'composite-checkout-cart');
        $cart->add(
            id: 'mouse-pair',
            name: 'Mouse Pair',
            price: 1000,
            quantity: 2,
            attributes: [
                'components' => compositeComponentSnapshot([
                    ['product' => $mouse, 'quantity' => 2],
                ]),
            ],
            associatedModel: $parent,
        );
        $cart->add(id: 'standalone-mouse', name: 'Mouse', price: 500, quantity: 1, associatedModel: $mouse);

        $listener = new ReserveStockOnCheckout($this->allocationService);
        $listener->handle(new class($cart)
        {
            public function __construct(public Cart $cart) {}
        });

        expect(InventoryAllocation::query()
            ->where('inventoryable_type', $mouse->getMorphClass())
            ->where('inventoryable_id', $mouse->getKey())
            ->sum('quantity'))->toBe(5)
            ->and(InventoryAllocation::query()
                ->where('inventoryable_type', $parent->getMorphClass())
                ->where('inventoryable_id', $parent->getKey())
                ->count())->toBe(0)
            ->and($cart->getMetadata('inventory_allocations')['allocations'])->toHaveKeys(['mouse-pair', 'standalone-mouse']);
    });
});
