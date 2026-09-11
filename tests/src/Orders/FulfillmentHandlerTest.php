<?php

declare(strict_types=1);

use AIArmada\FilamentShipping\Pages\FulfillmentQueue;
use AIArmada\Orders\Contracts\FulfillmentHandler;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Integrations\OrderFulfillmentHandler;
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
