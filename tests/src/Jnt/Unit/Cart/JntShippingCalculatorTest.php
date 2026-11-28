<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Features\LiveCart\Persistence\DatabaseStorage;
use AIArmada\Cart\Support\CartMoney;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\Jnt\Data\AddressData;
use AIArmada\Jnt\EntryPoints\Cart\JntShippingCalculator;
use Illuminate\Support\Facades\DB;

function createJntTestCart(string $identifier = 'test-user'): Cart
{
    $storage = new DatabaseStorage(DB::connection('testing'), 'carts');

    return new Cart(
        storage: $storage,
        identifier: $identifier,
        events: null,
        instanceName: 'default'
    );
}

describe('JntShippingCalculator', function (): void {
    beforeEach(function (): void {
        config([
            'jnt.shipping.base_rate' => 800,
            'jnt.shipping.per_kg_rate' => 200,
            'jnt.shipping.min_charge' => 800,
            'jnt.shipping.default_estimated_days' => 3,
            'jnt.shipping.default_service_name' => 'J&T Express',
            'jnt.shipping.default_service_type' => 'EZ',
            'jnt.shipping.origin' => [
                'name' => 'Test Sender',
                'phone' => '0123456789',
                'address' => '123 Test Street',
                'post_code' => '50000',
                'country_code' => 'MYS',
                'state' => 'Kuala Lumpur',
            ],
            'jnt.shipping.region_multipliers_bp' => [
                'sabah' => 15000,
                'sarawak' => 15000,
            ],
        ]);
    });

    it('calculates cart weight from items correctly', function (): void {
        $cart = createJntTestCart('weight-test-1');

        // Add items with weight attribute
        $cart->add([
            'id' => 'item-1',
            'name' => 'Item 1',
            'price' => 100,
            'quantity' => 2,
            'attributes' => ['weight' => 500],
        ]);

        $cart->add([
            'id' => 'item-2',
            'name' => 'Item 2',
            'price' => 200,
            'quantity' => 1,
            'attributes' => ['weight' => 1000],
        ]);

        $calculator = new JntShippingCalculator;
        $weight = $calculator->getCartWeight($cart);

        expect($weight)->toBe(2000); // (2 * 500) + (1 * 1000)
    });

    it('returns zero weight for empty cart', function (): void {
        $cart = createJntTestCart('empty-weight');

        $calculator = new JntShippingCalculator;
        $weight = $calculator->getCartWeight($cart);

        expect($weight)->toBe(0);
    });

    it('handles items without weight attribute', function (): void {
        $cart = createJntTestCart('no-weight-attr');
        $cart->add([
            'id' => 'no-weight-item',
            'name' => 'No Weight',
            'price' => 100,
            'quantity' => 2,
        ]);

        $calculator = new JntShippingCalculator;
        $weight = $calculator->getCartWeight($cart);

        expect($weight)->toBe(0);
    });

    it('calculates shipping for peninsular Malaysia', function (): void {
        $cart = createJntTestCart('peninsular-test');
        $cart->add([
            'id' => 'weighted-item',
            'name' => 'Weighted Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 1500],
        ]);

        $destination = new AddressData(
            name: 'Test Receiver',
            phone: '0123456789',
            address: '456 Test Avenue',
            postCode: '50000',
            state: 'Kuala Lumpur',
        );

        $calculator = new JntShippingCalculator;
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote)->not->toBeNull();
        expect($quote['service_name'])->toBe('J&T Express');
        expect($quote['service_type'])->toBe('EZ');
        expect($quote['weight_kg'])->toBe(1.5);
        expect($quote['estimated_days'])->toBe(3);
        expect($quote['amount'])->toBeGreaterThanOrEqual(800);
    });

    it('applies east Malaysia multiplier for Sabah', function (): void {
        $cart = createJntTestCart('sabah-test');
        $cart->add([
            'id' => 'sabah-item',
            'name' => 'Sabah Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 1000],
        ]);

        $peninsularDest = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
            state: 'Kuala Lumpur',
        );

        $sabahDest = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '88000',
            state: 'Sabah',
        );

        $calculator = new JntShippingCalculator;

        $peninsularQuote = $calculator->calculateShipping($cart, $peninsularDest);
        $sabahQuote = $calculator->calculateShipping($cart, $sabahDest);

        expect($sabahQuote['amount'])->toBeGreaterThan($peninsularQuote['amount']);
        expect($sabahQuote['estimated_days'])->toBe(5); // 3 + 2 for East Malaysia
    });

    it('returns null when cart has zero weight', function (): void {
        $cart = createJntTestCart('zero-weight');

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator;
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote)->toBeNull();
    });

    it('returns null when origin address not configured', function (): void {
        config(['jnt.shipping.origin' => null]);

        $cart = createJntTestCart('no-origin');
        $cart->add([
            'id' => 'origin-test',
            'name' => 'Origin Test',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 1000],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator;
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote)->toBeNull();
    });

    it('includes quote ID and timestamp', function (): void {
        $cart = createJntTestCart('quote-id-test');
        $cart->add([
            'id' => 'quote-test-item',
            'name' => 'Quote Test',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 1000],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator;
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote)->toHaveKey('quote_id');
        expect($quote)->toHaveKey('calculated_at');
        expect($quote)->toHaveKey('cart_weight');
        expect($quote['quote_id'])->toStartWith('jnt_quote_');
    });
});

describe('JntShippingCalculator Weight-Based Pricing', function (): void {
    beforeEach(function (): void {
        config([
            'jnt.shipping.base_rate' => 800,
            'jnt.shipping.per_kg_rate' => 200,
            'jnt.shipping.min_charge' => 800,
            'jnt.shipping.origin' => [
                'name' => 'Test',
                'phone' => '0123456789',
                'address' => 'Test',
                'post_code' => '50000',
            ],
        ]);
    });

    it('charges base rate for first kg', function (): void {
        $cart = createJntTestCart('base-rate-test');
        $cart->add([
            'id' => 'base-item',
            'name' => 'Base Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 800],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator;
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote['amount'])->toBe(800);
    });

    it('adds per-kg rate for additional weight', function (): void {
        $cart = createJntTestCart('per-kg-test');
        $cart->add([
            'id' => 'heavy-item',
            'name' => 'Heavy Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 2500],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator;
        $quote = $calculator->calculateShipping($cart, $destination);

        // 2.5kg rounds up to 3kg: base(800) + 2*perKg(200) = 1200
        expect($quote['amount'])->toBe(1200);
    });

    it('enforces minimum charge', function (): void {
        config([
            'jnt.shipping.base_rate' => 500,
            'jnt.shipping.min_charge' => 800,
        ]);

        $cart = createJntTestCart('min-charge-test');
        $cart->add([
            'id' => 'light-item',
            'name' => 'Light Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 100],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '50000',
        );

        $calculator = new JntShippingCalculator;
        $quote = $calculator->calculateShipping($cart, $destination);

        expect($quote['amount'])->toBe(800);
    });

    it('rounds a half-minor-unit regional multiplier upward', function (): void {
        config([
            'jnt.shipping.base_rate' => 1,
            'jnt.shipping.per_kg_rate' => 0,
            'jnt.shipping.min_charge' => 0,
            'jnt.shipping.region_multipliers_bp' => ['sabah' => 15000],
        ]);

        $cart = createJntTestCart('half-sen-round-up');
        $cart->add([
            'id' => 'half-sen-item',
            'name' => 'Half Sen Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 1000],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '88000',
            state: 'Sabah',
        );

        $quote = (new JntShippingCalculator)->calculateShipping($cart, $destination);

        expect($quote)->not->toBeNull()
            ->and($quote['amount'])->toBe(2);
    });

    it('rounds just below a half-minor-unit multiplier downward', function (): void {
        config([
            'jnt.shipping.base_rate' => 1,
            'jnt.shipping.per_kg_rate' => 0,
            'jnt.shipping.min_charge' => 0,
            'jnt.shipping.region_multipliers_bp' => ['sabah' => 14999],
        ]);

        $cart = createJntTestCart('half-sen-round-down');
        $cart->add([
            'id' => 'just-under-half-sen-item',
            'name' => 'Just Under Half Sen Item',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['weight' => 1000],
        ]);

        $destination = new AddressData(
            name: 'Test',
            phone: '0123456789',
            address: 'Test',
            postCode: '88000',
            state: 'Sabah',
        );

        $quote = (new JntShippingCalculator)->calculateShipping($cart, $destination);

        expect($quote)->not->toBeNull()
            ->and($quote['amount'])->toBe(1);
    });
});

it('uses canonical gram weights and ignores nonphysical items', function (): void {
    $cart = createJntTestCart('canonical-grams');
    $cart->add(['id' => 'physical', 'name' => 'Physical', 'price' => 100, 'quantity' => 2, 'attributes' => ['weight_grams' => 750, 'weight' => 10]]);
    $cart->add(['id' => 'service', 'name' => 'Service', 'price' => 100, 'quantity' => 1, 'attributes' => ['requires_shipping' => false, 'weight' => 5000]]);
    $cart->add(['id' => 'negative', 'name' => 'Negative', 'price' => 100, 'quantity' => 1, 'attributes' => ['weight' => -100]]);

    expect((new JntShippingCalculator)->getCartWeight($cart))->toBe(1500);
});

it('uses only physical composite components and expands their quantities', function (): void {
    $cart = new Cart(new InMemoryStorage, 'composite-grams');
    $component = [
        'product_id' => 'physical-product',
        'variant_id' => null,
        'inventoryable_type' => 'product',
        'inventoryable_id' => 'physical-product',
        'quantity' => 3,
        'sku' => 'PHYSICAL',
        'name' => 'Physical component',
        'weight' => 200,
        'dimensions' => null,
        'requires_shipping' => true,
        'tracks_inventory' => true,
    ];
    $service = array_replace($component, [
        'product_id' => 'service-product',
        'inventoryable_id' => 'service-product',
        'sku' => 'SERVICE',
        'name' => 'Service component',
        'requires_shipping' => false,
        'weight' => 5000,
    ]);

    $buyable = Mockery::mock(Buyable::class);
    $buyable->shouldReceive('getBuyableIdentifier')->andReturn('composite');
    $buyable->shouldReceive('getBuyableCatalogIdentifier')->andReturnNull();
    $buyable->shouldReceive('getBuyableName')->andReturn('Composite');
    $buyable->shouldReceive('getBuyablePrice')->andReturn(100);
    $buyable->shouldReceive('getBuyableCurrency')->andReturn(CartMoney::currency());
    $buyable->shouldReceive('getBuyableAttributes')->andReturn([
        'weight' => 9999,
        'components' => [$component, $service],
    ]);
    $buyable->shouldReceive('canBePurchased')->with(2)->andReturnTrue();
    $buyable->shouldReceive('getBuyableMinimumQuantity')->andReturn(1);
    $buyable->shouldReceive('getBuyableMaximumQuantity')->andReturnNull();
    $buyable->shouldReceive('getBuyableQuantityIncrement')->andReturn(1);
    $buyable->shouldReceive('getBuyableStock')->andReturnNull();

    $line = $cart->addBuyable($buyable, 2);

    expect($line->getAttribute('components'))->toHaveCount(2);

    expect((new JntShippingCalculator)->getCartWeight($cart))->toBe(1200);
});

it('does not trust composite components injected through raw cart additions', function (): void {
    $cart = createJntTestCart('raw-composite-grams');
    $line = $cart->add([
        'id' => 'raw-composite',
        'name' => 'Raw composite',
        'price' => 100,
        'quantity' => 2,
        'attributes' => [
            'weight' => 750,
            'components' => [['requires_shipping' => false]],
        ],
    ]);

    expect($line->getAttribute('components'))->toBeNull()
        ->and((new JntShippingCalculator)->getCartWeight($cart))->toBe(1500);
});
