<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Jnt\Data\AddressData as JntAddressData;
use AIArmada\Jnt\Drivers\JntShippingDriver;
use AIArmada\Jnt\EntryPoints\Cart\JntShippingCalculator;
use AIArmada\Jnt\Features\Tracking\Actions\JntTrackingService;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;

it('keeps driver and cart quotes identical for the same weight and destination', function (): void {
    config([
        'jnt.shipping.base_rate' => 800,
        'jnt.shipping.per_kg_rate' => 200,
        'jnt.shipping.min_charge' => 800,
        'jnt.shipping.default_estimated_days' => 3,
        'jnt.shipping.east_malaysia_extra_days' => 2,
        'jnt.shipping.region_multipliers_bp' => ['sabah' => 15000],
        'jnt.shipping.origin' => [
            'name' => 'Origin',
            'phone' => '0123456789',
            'address' => '1 Origin Street',
            'post_code' => '50000',
            'country_code' => 'MYS',
        ],
    ]);

    $shopAddress = new AddressData(
        name: 'Receiver',
        phone: '0123456789',
        line1: '1 Receiver Street',
        postcode: '88000',
        state: 'Sabah',
        country: 'MYS',
    );

    $driver = new JntShippingDriver(
        Mockery::mock(JntExpressService::class),
        Mockery::mock(JntTrackingService::class),
    );

    $driverRate = $driver->getRates(
        new AddressData(name: 'Origin', phone: '0123456789', line1: '1 Origin Street', postcode: '50000', country: 'MYS'),
        $shopAddress,
        [new PackageData(weight: 2500, quantity: 1)],
    )[0];

    $cart = new Cart(new InMemoryStorage, 'jnt-price-parity');
    $cart->add([
        'id' => 'parity-item',
        'name' => 'Parity item',
        'price' => 100,
        'quantity' => 1,
        'attributes' => ['weight' => 2500],
    ]);

    $cartQuote = (new JntShippingCalculator)->calculateShipping($cart, new JntAddressData(
        name: 'Receiver',
        phone: '0123456789',
        address: '1 Receiver Street',
        postCode: '88000',
        state: 'Sabah',
    ));

    expect($cartQuote['amount'])->toBe($driverRate->rate)
        ->and($cartQuote['estimated_days'])->toBe($driverRate->estimatedDays);
});
