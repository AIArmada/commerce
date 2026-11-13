<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\BuyablePriceResolver;
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\Cart\Exceptions\ProductNotPurchasableException;
use AIArmada\Cart\Support\BuyablePriceData;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\Buyable;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\Cart\InMemoryStorage;

/**
 * Create a mock buyable product for testing.
 */
function createTestBuyable(
    string $id = 'product-1',
    string $name = 'Test Product',
    int $price = 1000,
    ?int $stock = 100,
    int $minQty = 1,
    ?int $maxQty = null,
    int $increment = 1,
    bool $purchasable = true,
    string $currency = 'USD',
    ?string $catalogId = null,
    ?string $ownerType = null,
    string | int | null $ownerId = null,
): Buyable {
    return new class($id, $name, $price, $stock, $minQty, $maxQty, $increment, $purchasable, $currency, $catalogId, $ownerType, $ownerId) extends Model implements Buyable
    {
        public $timestamps = false;

        protected $guarded = [];

        public function __construct(
            private string $buyableId = 'test-buyable',
            private string $buyableName = 'Test buyable',
            private int $buyablePrice = 0,
            private ?int $buyableStock = null,
            private int $minQty = 1,
            private ?int $maxQty = null,
            private int $increment = 1,
            private bool $purchasable = true,
            private string $currency = 'MYR',
            private ?string $catalogId = null,
            ?string $ownerType = null,
            string | int | null $ownerId = null,
        ) {
            parent::__construct([
                'id' => $buyableId,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
            ]);
        }

        public function getBuyableIdentifier(): string
        {
            return $this->buyableId;
        }

        public function getBuyableCatalogIdentifier(): ?string
        {
            return $this->catalogId;
        }

        public function getBuyableName(): string
        {
            return $this->buyableName;
        }

        public function getBuyablePrice(): int
        {
            return $this->buyablePrice;
        }

        public function getBuyableCurrency(): string
        {
            return $this->currency;
        }

        public function getBuyableStock(): ?int
        {
            return $this->buyableStock;
        }

        public function canBePurchased(?int $quantity = null): bool
        {
            if (! $this->purchasable) {
                return false;
            }

            if ($quantity !== null && $this->buyableStock !== null && $quantity > $this->buyableStock) {
                return false;
            }

            return true;
        }

        public function getBuyableAttributes(): array
        {
            return [
                'sku' => 'TEST-SKU',
                'weight' => 500,
                'dimensions' => ['length' => 10, 'width' => 10, 'height' => 5],
                'requires_shipping' => true,
                'is_taxable' => true,
                'tax_class' => 'standard',
            ];
        }

        public function getBuyableMinimumQuantity(): int
        {
            return $this->minQty;
        }

        public function getBuyableMaximumQuantity(): ?int
        {
            return $this->maxQty;
        }

        public function getBuyableQuantityIncrement(): int
        {
            return $this->increment;
        }
    };
}

describe('ManagesBuyables Trait', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cart = new Cart($this->storage, 'user-buyable');
    });

    it('adds a buyable product to cart', function (): void {
        $buyable = createTestBuyable();

        $item = $this->cart->addBuyable($buyable);

        expect($item->id)->toBe('product-1')
            ->and($item->name)->toBe('Test Product')
            ->and($item->price)->toBe(1000)
            ->and($item->quantity)->toBe(1);
    });

    it('adds buyable with quantity', function (): void {
        $buyable = createTestBuyable();

        $item = $this->cart->addBuyable($buyable, 3);

        expect($item->quantity)->toBe(3);
    });

    it('adds buyable with extra attributes', function (): void {
        $buyable = createTestBuyable();

        $item = $this->cart->addBuyable($buyable, 1, ['color' => 'red']);

        expect($item->getAttribute('color'))->toBe('red')
            ->and($item->getAttribute('sku'))->toBe('TEST-SKU');
    });

    it('checks if buyable exists in cart', function (): void {
        $buyable = createTestBuyable();

        expect($this->cart->hasBuyable($buyable))->toBeFalse();

        $this->cart->addBuyable($buyable);

        expect($this->cart->hasBuyable($buyable))->toBeTrue();
    });

    it('gets buyable from cart', function (): void {
        $buyable = createTestBuyable();
        $this->cart->addBuyable($buyable);

        $item = $this->cart->getBuyable($buyable);

        expect($item)->not->toBeNull()
            ->and($item->id)->toBe('product-1');
    });

    it('returns null for non-existent buyable', function (): void {
        $buyable = createTestBuyable();

        expect($this->cart->getBuyable($buyable))->toBeNull();
    });

    it('removes buyable from cart', function (): void {
        $buyable = createTestBuyable();
        $this->cart->addBuyable($buyable);

        expect($this->cart->hasBuyable($buyable))->toBeTrue();

        $this->cart->removeBuyable($buyable);

        expect($this->cart->hasBuyable($buyable))->toBeFalse();
    });

    it('updates buyable quantity', function (): void {
        $buyable = createTestBuyable();
        $this->cart->addBuyable($buyable, 2);

        $this->cart->updateBuyable($buyable, 5);

        $item = $this->cart->getBuyable($buyable);
        expect($item->quantity)->toBe(5);
    });

    it('removes buyable when quantity is zero', function (): void {
        $buyable = createTestBuyable();
        $this->cart->addBuyable($buyable);

        $this->cart->updateBuyable($buyable, 0);

        expect($this->cart->hasBuyable($buyable))->toBeFalse();
    });

    it('throws when adding out of stock item', function (): void {
        $buyable = createTestBuyable(stock: 5);

        $this->cart->addBuyable($buyable, 10);
    })->throws(ProductNotPurchasableException::class);

    it('throws when adding inactive product', function (): void {
        $buyable = createTestBuyable(purchasable: false);

        $this->cart->addBuyable($buyable);
    })->throws(ProductNotPurchasableException::class);

    it('throws when below minimum quantity', function (): void {
        $buyable = createTestBuyable(minQty: 5);

        $this->cart->addBuyable($buyable, 2);
    })->throws(ProductNotPurchasableException::class);

    it('throws when above maximum quantity', function (): void {
        $buyable = createTestBuyable(maxQty: 3);

        $this->cart->addBuyable($buyable, 5);
    })->throws(ProductNotPurchasableException::class);

    it('throws when quantity increment is invalid', function (): void {
        $buyable = createTestBuyable(increment: 5);

        $this->cart->addBuyable($buyable, 7); // Not a multiple of 5
    })->throws(ProductNotPurchasableException::class);

    it('calculates total weight', function (): void {
        $buyable = createTestBuyable();

        $this->cart->addBuyable($buyable, 2); // 500g * 2 = 1000g

        expect($this->cart->getTotalWeight())->toBe(1000);
    });

    it('returns zero weight when no items have weight', function (): void {
        $this->cart->add('item-1', 'Product', 100, 1);

        $totalWeight = $this->cart->getTotalWeight();

        expect($totalWeight)->toBe(0);
    });

    it('calculates combined weight for multiple buyables', function (): void {
        $buyable1 = createTestBuyable(id: 'p1');
        $buyable2 = createTestBuyable(id: 'p2');

        $this->cart->addBuyable($buyable1, 2); // 500g * 2
        $this->cart->addBuyable($buyable2, 3); // 500g * 3

        expect($this->cart->getTotalWeight())->toBe(2500);
    });

    it('skips refresh for non-buyable items', function (): void {
        $this->cart->add('item-1', 'Regular Item', 500, 1);

        $changes = $this->cart->refreshBuyablePrices(fn () => createTestBuyable());

        expect($changes)->toBeEmpty();
    });

    it('skips validation for non-buyable items', function (): void {
        $this->cart->add('item-1', 'Regular Item', 500, 1);

        $errors = $this->cart->validateAllBuyables(fn () => createTestBuyable());

        expect($errors)->toBeEmpty();
    });

    it('validates the accumulated quantity before writing a repeated add', function (): void {
        $buyable = createTestBuyable(stock: 5);

        $this->cart->addBuyable($buyable, 3);

        expect(fn () => $this->cart->addBuyable($buyable, 3))
            ->toThrow(ProductNotPurchasableException::class);

        expect($this->cart->getBuyable($buyable)?->quantity)->toBe(3);
    });

    it('validates the accumulated quantity when updating a line', function (): void {
        $buyable = createTestBuyable(stock: 5);

        $this->cart->addBuyable($buyable, 4);

        expect(fn () => $this->cart->updateBuyable($buyable, 6))
            ->toThrow(ProductNotPurchasableException::class);

        expect($this->cart->getBuyable($buyable)?->quantity)->toBe(4);

        $this->cart->updateBuyable($buyable, 5);

        expect($this->cart->getBuyable($buyable)?->quantity)->toBe(5);
    });

    it('keeps distinct line identities while summing the same merchandise stock', function (): void {
        $buyable = createTestBuyable(stock: 5, catalogId: 'catalog-1');

        $this->cart->addBuyable($buyable, 2, [], 'ticket-a');
        $this->cart->addBuyable($buyable, 1, [], 'ticket-b');

        expect($this->cart->get('ticket-a')?->quantity)->toBe(2)
            ->and($this->cart->get('ticket-b')?->quantity)->toBe(1)
            ->and($this->cart->countItems())->toBe(2);

        expect(fn () => $this->cart->addBuyable($buyable, 3, [], 'ticket-b'))
            ->toThrow(ProductNotPurchasableException::class);

        expect($this->cart->get('ticket-b')?->quantity)->toBe(1);
    });

    it('sums individually purchased merchandise with ticket-attached merchandise', function (): void {
        $buyable = createTestBuyable(stock: 5);

        $this->cart->addBuyable($buyable, 2);
        $this->cart->addBuyable($buyable, 3, ['bundle_parent_line_id' => 'ticket-1'], 'ticket-bundle:ticket-1:test:product-1');

        expect($this->cart->getTotalQuantity())->toBe(5);

        expect(fn () => $this->cart->addBuyable($buyable, 1))
            ->toThrow(ProductNotPurchasableException::class);
    });

    it('rejects a buyable whose currency differs from the cart currency', function (): void {
        $buyable = createTestBuyable(currency: 'MYR');

        expect(fn () => $this->cart->addBuyable($buyable))
            ->toThrow(InvalidCartItemException::class, 'currency');
    });

    it('rejects a buyable owned by another owner when cart owner mode is enabled', function (): void {
        config()->set('cart.owner.enabled', true);

        $owner = new User;
        $owner->forceFill(['id' => 'owner-a']);
        $storage = (new InMemoryStorage)->withOwner($owner);
        $cart = new Cart($storage, 'owner-scoped-cart');

        $buyable = createTestBuyable(
            ownerType: $owner->getMorphClass(),
            ownerId: 'owner-b',
        );

        expect(fn () => $cart->addBuyable($buyable))
            ->toThrow(InvalidCartItemException::class, 'owner');
    });

    it('stores canonical line attributes and quotes through the pricing seam', function (): void {
        app()->instance(BuyablePriceResolver::class, new class implements BuyablePriceResolver
        {
            public function resolve(Buyable $buyable, int $quantity, array $context = []): BuyablePriceData
            {
                return new BuyablePriceData(
                    unitPrice: 900,
                    currency: 'USD',
                    originalUnitPrice: 1000,
                    discountAmount: 100,
                    discountSource: 'test',
                );
            }
        });

        $buyable = createTestBuyable(stock: 5, catalogId: 'catalog-1');

        $item = $this->cart->addBuyable($buyable, 2, ['bundle_source' => 'ticket_type:t1'], 'ticket-bundle:t1');

        expect($item->price)->toBe(900)
            ->and($item->quantity)->toBe(2)
            ->and($item->getAttribute('purchasable_id'))->toBe('product-1')
            ->and($item->getAttribute('catalog_id'))->toBe('catalog-1')
            ->and($item->getAttribute('variant_id'))->toBe('product-1')
            ->and($item->getAttribute('weight'))->toBe(500)
            ->and($item->getAttribute('dimensions'))->toBe(['length' => 10, 'width' => 10, 'height' => 5])
            ->and($item->getAttribute('requires_shipping'))->toBeTrue()
            ->and($item->getAttribute('is_taxable'))->toBeTrue()
            ->and($item->getAttribute('tax_class'))->toBe('standard')
            ->and($item->getAttribute('currency'))->toBe('USD')
            ->and($item->getAttribute('bundle_source'))->toBe('ticket_type:t1');
    });

    it('strips composition attributes from raw cart lines', function (): void {
        $injected = [
            'components' => [[
                'product_id' => 'expensive-item',
                'quantity' => 99,
                'tracks_inventory' => true,
            ]],
            'components_fingerprint' => 'forged-fingerprint',
            'legitimate' => 'kept',
        ];

        $item = $this->cart->add('raw-1', 'Raw line', 100, 1, $injected);

        expect($item->getAttribute('components'))->toBeNull()
            ->and($item->getAttribute('components_fingerprint'))->toBeNull()
            ->and($item->getAttribute('legitimate'))->toBe('kept');

        $this->cart->update('raw-1', ['attributes' => $injected]);

        expect($this->cart->get('raw-1')?->getAttribute('components'))->toBeNull();
    });
});
