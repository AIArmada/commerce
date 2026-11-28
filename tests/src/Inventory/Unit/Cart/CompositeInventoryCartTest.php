<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartValidationResult;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Features\Reservations\Actions\ReserveCartInventory;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Cart\CartManagerWithInventory;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Cart\InventoryValidator;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Listeners\ReserveStockOnCheckout;
use AIArmada\Inventory\Features\Reservations\Models\InventoryAllocation;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Queries\CompositeComponents;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;

it('recognizes only typed catalog composites under strict Eloquent attribute access', function (): void {
    $original = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes(true);

    try {
        $ticketLike = new class extends Model {};
        $ticketLike->setRawAttributes(['id' => 'ticket-like'], true);
        $ticketLike->exists = true;

        expect(CompositeComponents::isCompositeModel($ticketLike))->toBeFalse();

        $typed = new class extends Model {};
        $typed->setRawAttributes(['id' => 'catalog', 'type' => 'composite'], true);
        $typed->exists = true;

        expect(CompositeComponents::isCompositeModel($typed))->toBeTrue();

        $typed->setRawAttributes(['id' => 'catalog', 'type' => 'simple'], true);

        expect(CompositeComponents::isCompositeModel($typed))->toBeFalse();
    } finally {
        Model::preventAccessingMissingAttributes($original);
    }
});

final class NonTrackingCartInventoryItem extends InventoryItem
{
    public function tracksInventory(): bool
    {
        return false;
    }
}

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
        'product_id' => (string) $component['product']->getKey(),
        'variant_id' => null,
        'inventoryable_type' => $component['product']->getMorphClass(),
        'inventoryable_id' => (string) $component['product']->getKey(),
        'quantity' => $component['quantity'],
        'tracks_inventory' => true,
        'requires_shipping' => true,
        'name' => $component['product']->name,
        'sku' => 'SKU-' . (string) $component['product']->getKey(),
        'weight' => 100,
        'dimensions' => ['length' => 10, 'width' => 10, 'height' => 10],
    ], $components);
}

/**
 * Build the canonical buyable a catalog composite product exposes, so cart
 * lines are created through `addBuyable()` instead of forging attributes.
 *
 * @param  list<array<string, mixed>>  $componentSnapshot
 */
function compositeCartBuyable(string $id, string $name, int $price, array $componentSnapshot, Model $parent): Buyable
{
    return new class($id, $name, $price, $componentSnapshot, $parent) implements Buyable
    {
        /**
         * @param  list<array<string, mixed>>  $componentSnapshot
         */
        public function __construct(
            private readonly string $identifier,
            private readonly string $buyableName,
            private readonly int $unitPrice,
            private readonly array $componentSnapshot,
            private readonly Model $parent,
        ) {}

        public function getBuyableIdentifier(): string
        {
            return $this->identifier;
        }

        public function getBuyableCatalogIdentifier(): ?string
        {
            return (string) $this->parent->getKey();
        }

        public function getBuyableName(): string
        {
            return $this->buyableName;
        }

        public function getBuyablePrice(): int
        {
            return $this->unitPrice;
        }

        public function getBuyableCurrency(): string
        {
            return 'USD';
        }

        public function getBuyableAttributes(): array
        {
            return [
                'components' => $this->componentSnapshot,
                'inventoryable_type' => $this->parent->getMorphClass(),
                'inventoryable_id' => (string) $this->parent->getKey(),
                'product_id' => (string) $this->parent->getKey(),
            ];
        }

        public function canBePurchased(?int $quantity = null): bool
        {
            return true;
        }

        public function getBuyableStock(): ?int
        {
            return null;
        }

        public function getBuyableMinimumQuantity(): int
        {
            return 1;
        }

        public function getBuyableMaximumQuantity(): ?int
        {
            return null;
        }

        public function getBuyableQuantityIncrement(): int
        {
            return 1;
        }
    };
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
        $cart->addBuyable(compositeCartBuyable('mouse-bundle', 'Mouse Bundle', 2000, compositeComponentSnapshot([
            ['product' => $mouse, 'quantity' => 2],
            ['product' => $cable, 'quantity' => 1],
        ]), $parent), 2);
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
        $cart->addBuyable(compositeCartBuyable('mouse-bundle', 'Mouse Bundle', 2000, compositeComponentSnapshot([
            ['product' => $mouse, 'quantity' => 2],
            ['product' => $cable, 'quantity' => 1],
        ]), $parent), 2);
        $cart->add(id: 'standalone-mouse', name: 'Mouse', price: 500, quantity: 1, associatedModel: $mouse);

        $manager = CartManagerWithInventory::fromCartManager(new CompositeFakeCartManager($cart));

        expect($manager->validateInventory()['available'])->toBeFalse()
            ->and(fn (): array => $manager->allocateAllInventory())->toThrow(InsufficientInventoryException::class)
            ->and(InventoryAllocation::query()->count())->toBe(0);
    });

    it('does not require stock or allocate non-tracked standalone items', function (): void {
        config()->set('inventory.cart.allow_backorder', false);
        config()->set('inventory.cart.validate_on_add', false);

        $service = NonTrackingCartInventoryItem::create(['name' => 'Untracked Service']);
        $cart = new Cart(new InMemoryStorage, 'non-tracked-stock-cart');
        $cart->add(id: 'service', name: 'Service', price: 500, quantity: 2, associatedModel: $service);

        $validator = new InventoryValidator($this->allocationService);
        $manager = CartManagerWithInventory::fromCartManager(new CompositeFakeCartManager($cart));

        expect($validator->validateCart($cart)->isValid)->toBeTrue()
            ->and($manager->allocateAllInventory())->toBeEmpty()
            ->and(InventoryAllocation::query()->count())->toBe(0);
    });

    it('counts only the current cart allocations when validating reserved stock', function (): void {
        config()->set('inventory.cart.allow_backorder', false);
        config()->set('inventory.cart.validate_on_add', false);

        $mouse = InventoryItem::create(['name' => 'Reserved Mouse']);
        $this->inventoryService->receive($mouse, $this->location->id, 5);

        $cart = new Cart(new InMemoryStorage, 'reserved-stock-cart');
        $cart->add(id: 'mouse', name: 'Mouse', price: 500, quantity: 5, associatedModel: $mouse);

        $manager = CartManagerWithInventory::fromCartManager(new CompositeFakeCartManager($cart));
        $manager->allocateAllInventory();

        $validator = new InventoryValidator($this->allocationService);

        $otherCart = new Cart(new InMemoryStorage, 'other-stock-cart');
        $otherCart->add(id: 'mouse', name: 'Mouse', price: 500, quantity: 1, associatedModel: $mouse);

        expect($validator->validateCart($cart)->isValid)->toBeTrue()
            ->and($validator->validateCart($otherCart)->isValid)->toBeFalse();
    });

    it('rejects an empty composite snapshot before writing the cart', function (): void {
        $parent = InventoryItem::create(['name' => 'Empty Bundle']);
        $this->inventoryService->receive($parent, $this->location->id, 10);

        $cart = new Cart(new InMemoryStorage, 'empty-composite-cart');
        expect(fn () => $cart->addBuyable(
            compositeCartBuyable('empty-bundle', 'Empty Bundle', 1000, [], $parent),
            3,
        ))->toThrow(InvalidArgumentException::class, 'requires a non-empty list');

        expect($cart->isEmpty())->toBeTrue()
            ->and(InventoryAllocation::query()->count())->toBe(0)
            ->and($this->inventoryService->getLevel($parent, $this->location->id)?->fresh()->quantity_reserved)->toBe(0);
    });

    it('validates the combined component and standalone demand', function (): void {
        $mouse = InventoryItem::create(['name' => 'Mouse']);
        $parent = InventoryItem::create(['name' => 'Mouse Pair']);

        $this->inventoryService->receive($mouse, $this->location->id, 5);
        $this->inventoryService->receive($parent, $this->location->id, 10);

        $cart = new Cart(new InMemoryStorage, 'composite-validator-cart');
        $cart->addBuyable(compositeCartBuyable('mouse-pair', 'Mouse Pair', 1000, compositeComponentSnapshot([
            ['product' => $mouse, 'quantity' => 2],
        ]), $parent), 2);
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
        $cart->addBuyable(compositeCartBuyable('mouse-pair', 'Mouse Pair', 1000, compositeComponentSnapshot([
            ['product' => $mouse, 'quantity' => 2],
        ]), $parent), 2);

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
        $cart->addBuyable(compositeCartBuyable('mouse-pair', 'Mouse Pair', 1000, compositeComponentSnapshot([
            ['product' => $mouse, 'quantity' => 2],
        ]), $parent), 2);
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
        $cart->addBuyable(compositeCartBuyable('mouse-pair', 'Mouse Pair', 1000, compositeComponentSnapshot([
            ['product' => $mouse, 'quantity' => 2],
        ]), $parent), 2);
        $cart->add(id: 'standalone-mouse', name: 'Mouse', price: 500, quantity: 1, associatedModel: $mouse);

        $listener = app(ReserveStockOnCheckout::class);
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

    it('rolls back the entire checkout allocation when a later item has insufficient stock', function (): void {
        config()->set('inventory.cart.reserve_on_checkout', true);
        config()->set('inventory.cart.validate_on_add', false);
        config()->set('inventory.cart.block_checkout_on_insufficient', true);

        $available = InventoryItem::create(['name' => 'Available']);
        $unavailable = InventoryItem::create(['name' => 'Unavailable']);
        $this->inventoryService->receive($available, $this->location->id, 5);

        $cart = new Cart(new InMemoryStorage, 'atomic-checkout-cart');
        $cart->add(id: 'available', name: 'Available', price: 100, quantity: 2, associatedModel: $available);
        $cart->add(id: 'unavailable', name: 'Unavailable', price: 100, quantity: 1, associatedModel: $unavailable);

        $event = new class($cart)
        {
            public function __construct(public Cart $cart) {}
        };

        expect(fn () => app(ReserveStockOnCheckout::class)->handle($event))
            ->toThrow(InsufficientInventoryException::class);

        expect($this->allocationService->getAllocationsForCart((string) $cart->getId()))->toBeEmpty()
            ->and($this->inventoryService->getLevel($available, $this->location->id)?->fresh()->quantity_reserved)->toBe(0);
    });
});

it('rebuilds checkout allocation metadata across all composite components and removes stale entries', function (): void {
    config()->set('inventory.cart.validate_on_add', false);
    config()->set('inventory.cart.block_checkout_on_insufficient', true);

    $mouse = InventoryItem::create(['name' => 'Metadata Mouse']);
    $cable = InventoryItem::create(['name' => 'Metadata Cable']);
    $parent = InventoryItem::create(['name' => 'Metadata Bundle']);
    $this->inventoryService->receive($mouse, $this->location->id, 10);
    $this->inventoryService->receive($cable, $this->location->id, 10);

    config()->set('cart.empty_cart_behavior', 'preserve');
    $cart = new Cart(new InMemoryStorage, 'composite-metadata-cart');
    $cart->addBuyable(compositeCartBuyable('metadata-bundle', 'Bundle', 1000, compositeComponentSnapshot([
        ['product' => $mouse, 'quantity' => 2],
        ['product' => $cable, 'quantity' => 1],
    ]), $parent), 2);

    $action = app(ReserveCartInventory::class);
    $action->execute($cart);

    $metadata = $cart->getMetadata('inventory_allocations')['allocations'];
    expect($metadata['metadata-bundle']['quantity'])->toBe(6)
        ->and($metadata['metadata-bundle']['allocation_count'])->toBe(2);

    $cart->remove('metadata-bundle');
    $action->execute($cart);

    expect($cart->getMetadata('inventory_allocations')['allocations'])->toBe([])
        ->and($this->inventoryService->getLevel($mouse, $this->location->id)?->fresh()->quantity_reserved)->toBe(0)
        ->and($this->inventoryService->getLevel($cable, $this->location->id)?->fresh()->quantity_reserved)->toBe(0);
});

it('releases removed identities when the cart is allocated again', function (): void {
    config()->set('inventory.cart.validate_on_add', false);

    $mouse = InventoryItem::create(['name' => 'Removed Mouse']);
    $cable = InventoryItem::create(['name' => 'Retained Cable']);
    $this->inventoryService->receive($mouse, $this->location->id, 10);
    $this->inventoryService->receive($cable, $this->location->id, 10);

    $cart = new Cart(new InMemoryStorage, 'reallocate-removed-cart');
    $cart->add(id: 'mouse', name: 'Mouse', price: 100, quantity: 2, associatedModel: $mouse);
    $cart->add(id: 'cable', name: 'Cable', price: 100, quantity: 3, associatedModel: $cable);

    $manager = CartManagerWithInventory::fromCartManager(new CompositeFakeCartManager($cart));
    $manager->allocateAllInventory();
    $cart->remove('mouse');
    $manager->allocateAllInventory();

    expect($this->inventoryService->getLevel($mouse, $this->location->id)?->fresh()->quantity_reserved)->toBe(0)
        ->and($this->inventoryService->getLevel($cable, $this->location->id)?->fresh()->quantity_reserved)->toBe(3);
});
