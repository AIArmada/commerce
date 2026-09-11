<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\FilamentShipping\Pages\FulfillmentQueue;
use AIArmada\Orders\Contracts\FulfillmentHandler;
use AIArmada\Orders\Models\Order;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Integrations\OrderFulfillmentHandler;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\ShipmentService;
use AIArmada\Shipping\ShippingManager;

it('exposes the carrier discovery seam on the fulfillment contract', function (): void {
    $shippingManager = Mockery::mock(ShippingManager::class);
    $shipmentService = Mockery::mock(ShipmentService::class);
    $manualDriver = Mockery::mock(ShippingDriverInterface::class);

    $manualDriver->shouldReceive('getCarrierCode')->once()->andReturn('manual');
    $manualDriver->shouldReceive('getCarrierName')->once()->andReturn('Manual Carrier');
    $shippingManager->shouldReceive('getAvailableDrivers')->once()->andReturn(['manual', 'broken']);
    $shippingManager->shouldReceive('driver')->with('manual')->once()->andReturn($manualDriver);
    $shippingManager->shouldReceive('driver')->with('broken')->once()->andThrow(new RuntimeException('driver unavailable'));

    $handler = new OrderFulfillmentHandler($shippingManager, $shipmentService);

    expect((new ReflectionClass(FulfillmentHandler::class))->hasMethod('availableCarriers'))->toBeTrue()
        ->and($handler->availableCarriers())->toBe(['manual' => 'Manual Carrier']);
});

it('keeps the fulfillment queue fallback when a handler has no carrier method', function (): void {
    app()->instance(FulfillmentHandler::class, new class {});

    $method = new ReflectionMethod(FulfillmentQueue::class, 'getCarrierOptions');
    $method->setAccessible(true);

    expect($method->invoke(new FulfillmentQueue))->toBe([]);
});

it('maps the canonical shipping attachment into shipment data', function (): void {
    $shippingManager = Mockery::mock(ShippingManager::class)->shouldIgnoreMissing();
    $shipmentService = Mockery::mock(ShipmentService::class);
    $shipment = new Shipment;
    $shipment->id = 'shipment-123';
    $shipment->tracking_number = 'TRACK-123';

    $shipmentService->shouldReceive('create')
        ->once()
        ->withArgs(function (ShipmentData $data, ?string $ownerId, ?string $ownerType): bool {
            return $data->destination->name === 'Shipping Customer'
                && $data->destination->company === 'ACME Corp'
                && $data->destination->line1 === '456 Shipping Road'
                && $data->destination->city === 'Johor Bahru'
                && $data->destination->postcode === '80000'
                && $data->destination->phone === '0123456789'
                && $data->destination->email === 'shipping@example.com'
                && $ownerId !== null
                && $ownerType !== null;
        })
        ->andReturn($shipment);
    $shipmentService->shouldReceive('markPending')->once()->with($shipment)->andReturn($shipment);
    $shipmentService->shouldReceive('ship')->once()->with($shipment)->andReturn($shipment);

    $order = Order::factory()->create(['grand_total' => 5000]);
    $address = Address::create([
        'line1' => '456 Shipping Road',
        'city' => 'Johor Bahru',
        'postcode' => '80000',
        'country_code' => 'MY',
        'metadata' => [
            Order::ADDRESS_CONTACT_METADATA_KEY => [
                'first_name' => 'Shipping',
                'last_name' => 'Customer',
                'company' => 'ACME Corp',
                'phone' => '0123456789',
                'email' => 'shipping@example.com',
            ],
        ],
    ]);
    $order->attachAddress($address, type: 'shipping', isPrimary: true);

    $handler = new OrderFulfillmentHandler($shippingManager, $shipmentService);
    $result = $handler->createShipment($order, ['carrier' => 'manual', 'service' => 'standard']);

    expect($result)->toMatchArray([
        'success' => true,
        'shipment_id' => 'shipment-123',
        'tracking_number' => 'TRACK-123',
        'error' => null,
    ]);
});

it('maps the canonical shipping attachment into carrier rate requests', function (): void {
    $shippingManager = Mockery::mock(ShippingManager::class);
    $shipmentService = Mockery::mock(ShipmentService::class);
    $driver = Mockery::mock(ShippingDriverInterface::class);

    $driver->shouldReceive('servicesDestination')
        ->once()
        ->withArgs(function (AddressData $destination): bool {
            return $destination->name === 'Shipping Customer'
                && $destination->line1 === '789 Shipping Avenue'
                && $destination->city === 'Kuala Lumpur'
                && $destination->postcode === '50000'
                && $destination->phone === '0123456789';
        })
        ->andReturnTrue();
    $driver->shouldReceive('getRates')
        ->once()
        ->andReturn(collect([
            new RateQuoteData(
                carrier: 'manual',
                service: 'standard',
                rate: 800,
                currency: 'MYR',
                estimatedDays: 3,
            ),
        ]));
    $shippingManager->shouldReceive('getAvailableDrivers')->once()->andReturn(['manual']);
    $shippingManager->shouldReceive('driver')->with('manual')->once()->andReturn($driver);

    $order = Order::factory()->create(['grand_total' => 5000]);
    $address = Address::create([
        'line1' => '789 Shipping Avenue',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country_code' => 'MY',
        'metadata' => [
            Order::ADDRESS_CONTACT_METADATA_KEY => [
                'first_name' => 'Shipping',
                'last_name' => 'Customer',
                'phone' => '0123456789',
            ],
        ],
    ]);
    $order->attachAddress($address, type: 'shipping', isPrimary: true);

    $handler = new OrderFulfillmentHandler($shippingManager, $shipmentService);
    $rates = $handler->getRates($order);

    expect($rates)->toBe([[
        'carrier' => 'manual',
        'service' => 'standard',
        'rate' => 800,
        'currency' => 'MYR',
    ]]);
});

it('keeps fulfillment addressless when no canonical shipping attachment exists', function (): void {
    $shippingManager = Mockery::mock(ShippingManager::class);
    $shipmentService = Mockery::mock(ShipmentService::class);
    $order = Order::factory()->create();
    $handler = new OrderFulfillmentHandler($shippingManager, $shipmentService);

    expect($handler->getRates($order))->toBeEmpty()
        ->and($handler->createShipment($order, []))->toMatchArray([
            'success' => false,
            'shipment_id' => null,
            'tracking_number' => null,
            'error' => 'Order has no shipping address',
        ]);
});
