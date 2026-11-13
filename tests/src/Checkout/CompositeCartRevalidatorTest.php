<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Support\CompositeCartRevalidator;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\Cart\InMemoryStorage;

final class RevalidatorCartManager implements CartManagerInterface
{
    public ?Cart $cart = null;

    public function getCurrentCart(): Cart
    {
        return $this->cart ?? throw new RuntimeException('No cart prepared.');
    }

    public function getCartInstance(string $name, ?string $identifier = null): Cart
    {
        return $this->getCurrentCart();
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
        return $this->cart;
    }

    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        return false;
    }
}

/**
 * @return array{0: Cart, 1: Product, 2: Product, 3: RevalidatorCartManager}
 */
function revalidatorScenario(): array
{
    static $sequence = 0;
    $sequence++;

    $makeComponent = static function (int $price) use ($sequence): Product {
        static $componentSequence = 0;
        $componentSequence++;

        return Product::query()->create([
            'name' => 'Revalidator Component ' . $componentSequence,
            'slug' => 'revalidator-component-' . $sequence . '-' . $componentSequence,
            'sku' => 'RVC-' . $sequence . '-' . $componentSequence,
            'type' => ProductType::Simple,
            'status' => ProductStatus::Active,
            'price' => $price,
            'currency' => 'USD',
            'requires_shipping' => true,
            'tracks_inventory' => true,
            'is_taxable' => true,
            'weight' => 0.25,
            'weight_unit' => 'kg',
        ]);
    };

    $mouse = $makeComponent(500);
    $cable = $makeComponent(300);

    $location = InventoryLocation::query()->firstOrCreate(
        ['code' => 'REVALIDATOR-WH'],
        ['name' => 'Revalidator WH', 'priority' => 100],
    );

    foreach ([$mouse, $cable] as $component) {
        app(InventoryService::class)->receive($component, $location->id, 10);
    }

    $package = app(CreateCompositeProduct::class)->handle([
        'name' => 'Revalidator Package',
        'slug' => 'revalidator-package-' . $sequence,
        'sku' => 'RVP-' . $sequence,
        'price' => 1200,
        'currency' => 'USD',
        'status' => ProductStatus::Active,
    ], [
        ['component_product_id' => (string) $mouse->getKey(), 'quantity' => 2],
    ]);

    $cart = new Cart(new InMemoryStorage, 'revalidator-cart-' . $sequence);
    $cart->addBuyable($package, 1);

    $manager = new RevalidatorCartManager;
    $manager->cart = $cart;

    return [$cart, $package, $cable, $manager];
}

function revalidatorSession(Cart $cart): CheckoutSession
{
    return CheckoutSession::forceCreate([
        'cart_id' => $cart->getId(),
        'cart_snapshot' => [
            'items' => $cart->getItems()->toArray(),
            'subtotal' => $cart->getRawSubtotal(),
            'total' => $cart->getRawTotal(),
        ],
        'subtotal' => $cart->getRawSubtotal(),
        'grand_total' => $cart->getRawTotal(),
        'currency' => 'USD',
    ]);
}

it('accepts an unchanged composite cart', function (): void {
    [$cart, , , $manager] = revalidatorScenario();
    $session = revalidatorSession($cart);

    expect((new CompositeCartRevalidator($manager))->validate($session))->toBe([]);
});

it('rejects a cart whose contents changed after checkout started', function (): void {
    [$cart, $package, , $manager] = revalidatorScenario();
    $session = revalidatorSession($cart);

    $cart->addBuyable($package, 1);

    expect((new CompositeCartRevalidator($manager))->validate($session))
        ->toBe([CompositeCartRevalidator::ERROR_KEY => CompositeCartRevalidator::CONTENTS_CHANGED]);
});

it('rejects a package whose catalog composition changed after checkout started', function (): void {
    [$cart, $package, $cable, $manager] = revalidatorScenario();
    $session = revalidatorSession($cart);

    ProductComponent::query()->create([
        'product_id' => $package->getKey(),
        'component_product_id' => $cable->getKey(),
        'quantity' => 1,
        'position' => 1,
    ]);

    expect((new CompositeCartRevalidator($manager))->validate($session))
        ->toBe([CompositeCartRevalidator::ERROR_KEY => CompositeCartRevalidator::CONTENTS_CHANGED]);
});

it('rejects a package whose catalog price changed after checkout started', function (): void {
    [$cart, $package, , $manager] = revalidatorScenario();
    $session = revalidatorSession($cart);

    $package->forceFill(['price' => 900])->save();

    expect((new CompositeCartRevalidator($manager))->validate($session))
        ->toBe([CompositeCartRevalidator::ERROR_KEY => CompositeCartRevalidator::PRICE_CHANGED]);
});
