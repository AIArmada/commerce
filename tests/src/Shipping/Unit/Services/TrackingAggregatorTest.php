<?php

declare(strict_types=1);

use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Contracts\StatusMapperInterface;
use AIArmada\Shipping\Enums\DriverCapability;
use AIArmada\Shipping\Enums\TrackingStatus;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\TrackingAggregator;
use AIArmada\Shipping\ShippingManager;
use AIArmada\Shipping\States\AwaitingPickup;
use AIArmada\Shipping\States\Delivered;
use AIArmada\Shipping\States\ExceptionStatus;
use AIArmada\Shipping\States\InTransit;
use AIArmada\Shipping\States\OutForDelivery;
use AIArmada\Shipping\States\ReturnToSender;
use AIArmada\Shipping\States\ShipmentStatus as ShipmentStatusState;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ============================================
// TrackingAggregator Tests
// ============================================

beforeEach(function (): void {
    $this->shippingManager = Mockery::mock(ShippingManager::class);
    $this->aggregator = new TrackingAggregator($this->shippingManager);
});

afterEach(function (): void {
    Mockery::close();
});

it('can register a status mapper', function (): void {
    $mapper = Mockery::mock(StatusMapperInterface::class);
    $mapper->shouldReceive('getCarrierCode')->andReturn('test_carrier');

    $this->aggregator->registerMapper($mapper);

    $retrieved = $this->aggregator->getMapper('test_carrier');

    expect($retrieved)->toBe($mapper);
});

it('returns null for unregistered mapper', function (): void {
    $mapper = $this->aggregator->getMapper('nonexistent');

    expect($mapper)->toBeNull();
});

it('returns shipment unchanged when no tracking number', function (): void {
    $shipment = Mockery::mock(Shipment::class)->makePartial();
    $shipment->tracking_number = null;

    $result = $this->aggregator->syncTracking($shipment);

    expect($result)->toBe($shipment);
});

it('returns shipment unchanged when driver does not support tracking', function (): void {
    $shipment = Mockery::mock(Shipment::class)->makePartial();
    $shipment->tracking_number = 'TRACK123';
    $shipment->carrier_code = 'test_carrier';

    $driver = Mockery::mock(ShippingDriverInterface::class);
    $driver->shouldReceive('supports')
        ->with(DriverCapability::Tracking)
        ->andReturn(false);

    $this->shippingManager->shouldReceive('driver')
        ->with('test_carrier')
        ->andReturn($driver);

    $result = $this->aggregator->syncTracking($shipment);

    expect($result)->toBe($shipment);
});

it('syncs tracking and updates shipment', function (): void {
    // This test requires a real database to properly test
    // because processTrackingEvents calls $shipment->events() which needs
    // a real HasMany relationship. This should be tested as an integration test.
})->skip('Requires database for proper Eloquent relationship mocking');

it('syncs batch of shipments', function (): void {
    $shipment1 = Mockery::mock(Shipment::class)->makePartial();
    $shipment1->id = 1;
    $shipment1->tracking_number = null;

    $shipment2 = Mockery::mock(Shipment::class)->makePartial();
    $shipment2->id = 2;
    $shipment2->tracking_number = null;

    $shipments = collect([$shipment1, $shipment2]);

    $results = $this->aggregator->syncBatch($shipments);

    expect($results)->toHaveCount(2);
    expect($results[0]['success'])->toBeTrue();
    expect($results[1]['success'])->toBeTrue();
});

it('handles errors in batch sync', function (): void {
    $shipment = Mockery::mock(Shipment::class)->makePartial();
    $shipment->id = 1;
    $shipment->tracking_number = 'TRACK123';
    $shipment->carrier_code = 'test_carrier';

    $driver = Mockery::mock(ShippingDriverInterface::class);
    $driver->shouldReceive('supports')
        ->with(DriverCapability::Tracking)
        ->andThrow(new Exception('API Error'));

    $this->shippingManager->shouldReceive('driver')
        ->with('test_carrier')
        ->andReturn($driver);

    $shipments = collect([$shipment]);

    $results = $this->aggregator->syncBatch($shipments);

    expect($results)->toHaveCount(1);
    expect($results[0]['success'])->toBeFalse();
    expect($results[0]['error'])->toBe('API Error');
});

it('maps tracking status to shipment status correctly', function (): void {
    // Test the mapping method via reflection
    $method = new ReflectionMethod(TrackingAggregator::class, 'mapTrackingToShipmentStatus');
    $method->setAccessible(true);

    $aggregator = new TrackingAggregator($this->shippingManager);

    expect($method->invoke($aggregator, TrackingStatus::AwaitingPickup))
        ->toBe(ShipmentStatusState::normalize(AwaitingPickup::class));
    expect($method->invoke($aggregator, TrackingStatus::InTransit))
        ->toBe(ShipmentStatusState::normalize(InTransit::class));
    expect($method->invoke($aggregator, TrackingStatus::OutForDelivery))
        ->toBe(ShipmentStatusState::normalize(OutForDelivery::class));
    expect($method->invoke($aggregator, TrackingStatus::Delivered))
        ->toBe(ShipmentStatusState::normalize(Delivered::class));
    expect($method->invoke($aggregator, TrackingStatus::Lost))
        ->toBe(ShipmentStatusState::normalize(ExceptionStatus::class));
    expect($method->invoke($aggregator, TrackingStatus::ReturnToSender))
        ->toBe(ShipmentStatusState::normalize(ReturnToSender::class));
});
