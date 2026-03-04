<?php

declare(strict_types=1);

use AIArmada\Shipping\Contracts\AddressValidationResult;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Drivers\ZoneBasedShippingDriver;
use AIArmada\Shipping\Enums\DriverCapability;
use AIArmada\Shipping\Enums\TrackingStatus;
use AIArmada\Shipping\Models\ShippingRate;
use AIArmada\Shipping\Models\ShippingZone;
use AIArmada\Shipping\Services\ShippingZoneResolver;

// ============================================
// ZoneBasedShippingDriver Tests
// ============================================

beforeEach(function (): void {
    $this->resolver = new ShippingZoneResolver;
    $this->driver = new ZoneBasedShippingDriver($this->resolver);

    $this->origin = new AddressData(
        name: 'Sender',
        phone: '+60123456789',
        line1: '123 Test St',
        postcode: '50000',
        country: 'MY',
        city: 'Kuala Lumpur',
        state: 'KL',
    );

    $this->destination = new AddressData(
        name: 'Receiver',
        phone: '+60198765432',
        line1: '456 Target St',
        postcode: '40000',
        country: 'MY',
        city: 'Shah Alam',
        state: 'Selangor',
    );
});

it('returns correct carrier code', function (): void {
    expect($this->driver->getCarrierCode())->toBe('zone');
});

it('returns correct carrier name', function (): void {
    expect($this->driver->getCarrierName())->toBe('Zone-Based Shipping');
});

it('returns custom carrier name from config', function (): void {
    $driver = new ZoneBasedShippingDriver($this->resolver, ['name' => 'Custom Zone Shipping']);

    expect($driver->getCarrierName())->toBe('Custom Zone Shipping');
});

it('supports rate quotes capability', function (): void {
    expect($this->driver->supports(DriverCapability::RateQuotes))->toBeTrue();
});

it('does not support tracking capability', function (): void {
    expect($this->driver->supports(DriverCapability::Tracking))->toBeFalse();
});

it('does not support label generation capability', function (): void {
    expect($this->driver->supports(DriverCapability::LabelGeneration))->toBeFalse();
});

it('returns zone_standard method', function (): void {
    $methods = $this->driver->getAvailableMethods();

    expect($methods)->toHaveCount(1);
    expect($methods->first()->code)->toBe('zone_standard');
    expect($methods->first()->name)->toBe('Zone-Based Shipping');
});

it('returns empty rates when no zones exist', function (): void {
    $packages = [new PackageData(weight: 1000)];

    $rates = $this->driver->getRates($this->origin, $this->destination, $packages);

    expect($rates)->toBeEmpty();
});

it('returns rates from matching zone', function (): void {
    $zone = ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'Malaysia Zone',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'carrier_code' => 'zone',
        'method_code' => 'standard',
        'name' => 'Standard Delivery',
        'calculation_type' => 'flat',
        'base_rate' => 800,
        'estimated_days_min' => 3,
        'active' => true,
    ]);

    $packages = [new PackageData(weight: 1000)];

    $rates = $this->driver->getRates(
        $this->origin,
        $this->destination,
        $packages,
        ['owner_id' => 'test-owner-123', 'owner_type' => 'TestOwner'],
    );

    expect($rates)->toHaveCount(1);
    expect($rates->first())->toBeInstanceOf(RateQuoteData::class);
    expect($rates->first()->carrier)->toBe('zone');
    expect($rates->first()->service)->toBe('standard');
    expect($rates->first()->rate)->toBe(800);
    expect($rates->first()->estimatedDays)->toBe(3);
    expect($rates->first()->serviceDescription)->toBe('Standard Delivery');
    expect($rates->first()->calculatedLocally)->toBeTrue();
});

it('returns multiple rates from one zone', function (): void {
    $zone = ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'Malaysia Zone',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'method_code' => 'standard',
        'name' => 'Standard',
        'calculation_type' => 'flat',
        'base_rate' => 800,
        'estimated_days_min' => 5,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'method_code' => 'express',
        'name' => 'Express',
        'calculation_type' => 'flat',
        'base_rate' => 1500,
        'estimated_days_min' => 1,
        'active' => true,
    ]);

    $packages = [new PackageData(weight: 1000)];

    $rates = $this->driver->getRates(
        $this->origin,
        $this->destination,
        $packages,
        ['owner_id' => 'test-owner-123', 'owner_type' => 'TestOwner'],
    );

    expect($rates)->toHaveCount(2);
    expect($rates->pluck('service')->all())->toContain('standard', 'express');
});

it('excludes inactive rates', function (): void {
    $zone = ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'Malaysia Zone',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'method_code' => 'standard',
        'name' => 'Active Rate',
        'calculation_type' => 'flat',
        'base_rate' => 800,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'method_code' => 'express',
        'name' => 'Inactive Rate',
        'calculation_type' => 'flat',
        'base_rate' => 1500,
        'active' => false,
    ]);

    $packages = [new PackageData(weight: 1000)];

    $rates = $this->driver->getRates(
        $this->origin,
        $this->destination,
        $packages,
        ['owner_id' => 'test-owner-123', 'owner_type' => 'TestOwner'],
    );

    expect($rates)->toHaveCount(1);
    expect($rates->first()->serviceDescription)->toBe('Active Rate');
});

it('filters rates by conditions', function (): void {
    $zone = ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'Malaysia Zone',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'method_code' => 'standard',
        'name' => 'Standard (no conditions)',
        'calculation_type' => 'flat',
        'base_rate' => 800,
        'conditions' => null,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'method_code' => 'heavy',
        'name' => 'Heavy Items Only',
        'calculation_type' => 'flat',
        'base_rate' => 2000,
        'conditions' => [
            ['type' => 'min_weight', 'value' => 5000],
        ],
        'active' => true,
    ]);

    $lightPackages = [new PackageData(weight: 1000)];

    $rates = $this->driver->getRates(
        $this->origin,
        $this->destination,
        $lightPackages,
        ['owner_id' => 'test-owner-123', 'owner_type' => 'TestOwner'],
    );

    expect($rates)->toHaveCount(1);
    expect($rates->first()->serviceDescription)->toBe('Standard (no conditions)');

    $heavyPackages = [new PackageData(weight: 6000)];

    $heavyRates = $this->driver->getRates(
        $this->origin,
        $this->destination,
        $heavyPackages,
        ['owner_id' => 'test-owner-123', 'owner_type' => 'TestOwner'],
    );

    expect($heavyRates)->toHaveCount(2);
});

it('passes cart_total and item_count to conditions', function (): void {
    $zone = ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'Malaysia Zone',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'method_code' => 'free',
        'name' => 'Free Shipping Over RM100',
        'calculation_type' => 'flat',
        'base_rate' => 0,
        'conditions' => [
            ['type' => 'min_order_total', 'value' => 10000],
        ],
        'active' => true,
    ]);

    $packages = [new PackageData(weight: 1000)];

    $lowCartRates = $this->driver->getRates(
        $this->origin,
        $this->destination,
        $packages,
        [
            'owner_id' => 'test-owner-123',
            'owner_type' => 'TestOwner',
            'cart_total' => 5000,
        ],
    );

    expect($lowCartRates)->toBeEmpty();

    $highCartRates = $this->driver->getRates(
        $this->origin,
        $this->destination,
        $packages,
        [
            'owner_id' => 'test-owner-123',
            'owner_type' => 'TestOwner',
            'cart_total' => 15000,
        ],
    );

    expect($highCartRates)->toHaveCount(1);
});

it('uses carrier_code from rate when available', function (): void {
    $zone = ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'Malaysia Zone',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'carrier_code' => 'custom_carrier',
        'method_code' => 'standard',
        'name' => 'Custom Carrier Rate',
        'calculation_type' => 'flat',
        'base_rate' => 900,
        'active' => true,
    ]);

    $packages = [new PackageData(weight: 1000)];

    $rates = $this->driver->getRates(
        $this->origin,
        $this->destination,
        $packages,
        ['owner_id' => 'test-owner-123', 'owner_type' => 'TestOwner'],
    );

    expect($rates->first()->carrier)->toBe('custom_carrier');
});

it('uses configured currency', function (): void {
    $driver = new ZoneBasedShippingDriver($this->resolver, ['currency' => 'USD']);

    $zone = ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'Malaysia Zone',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'method_code' => 'standard',
        'name' => 'Standard',
        'calculation_type' => 'flat',
        'base_rate' => 500,
        'active' => true,
    ]);

    $packages = [new PackageData(weight: 1000)];

    $rates = $driver->getRates(
        $this->origin,
        $this->destination,
        $packages,
        ['owner_id' => 'test-owner-123', 'owner_type' => 'TestOwner'],
    );

    expect($rates->first()->currency)->toBe('USD');
});

it('returns empty rates for unmatched destination', function (): void {
    $zone = ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'US Zone',
        'code' => 'US',
        'type' => 'country',
        'countries' => ['US'],
        'priority' => 10,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'method_code' => 'standard',
        'name' => 'US Only Rate',
        'calculation_type' => 'flat',
        'base_rate' => 1000,
        'active' => true,
    ]);

    $packages = [new PackageData(weight: 1000)];

    $rates = $this->driver->getRates(
        $this->origin,
        $this->destination,
        $packages,
        ['owner_id' => 'test-owner-123', 'owner_type' => 'TestOwner'],
    );

    expect($rates)->toBeEmpty();
});

it('services destination with matching zone', function (): void {
    ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'Malaysia Zone',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'active' => true,
    ]);

    expect($this->driver->servicesDestination($this->destination))->toBeTrue();
});

it('does not service destination without matching zone', function (): void {
    $jpDestination = new AddressData(
        name: 'Japan Receiver',
        phone: '+81123456789',
        line1: '1-1 Tokyo',
        postcode: '100-0001',
        country: 'JP',
    );

    expect($this->driver->servicesDestination($jpDestination))->toBeFalse();
});

it('validates address as valid for serviceable destination', function (): void {
    ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'Malaysia Zone',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'active' => true,
    ]);

    $result = $this->driver->validateAddress($this->destination);

    expect($result)->toBeInstanceOf(AddressValidationResult::class);
    expect($result->isValid())->toBeTrue();
});

it('validates address as invalid for unserviceable destination', function (): void {
    $result = $this->driver->validateAddress($this->destination);

    expect($result->isValid())->toBeFalse();
    expect($result->errors)->toContain('No shipping zone covers this address');
});

it('creates shipment with zone tracking number', function (): void {
    $shipmentData = new ShipmentData(
        reference: 'TEST-001',
        carrierCode: 'zone',
        serviceCode: 'zone_standard',
        origin: $this->origin,
        destination: $this->destination,
        items: [],
    );

    $result = $this->driver->createShipment($shipmentData);

    expect($result->isSuccessful())->toBeTrue();
    expect($result->trackingNumber)->toStartWith('ZONE-');
    expect($result->requiresManualFulfillment)->toBeTrue();
});

it('cancels shipment always returns true', function (): void {
    expect($this->driver->cancelShipment('ZONE-123ABC'))->toBeTrue();
});

it('generates label with no content', function (): void {
    $label = $this->driver->generateLabel('ZONE-123ABC');

    expect($label->format)->toBe('none');
    expect($label->url)->toBeNull();
    expect($label->content)->toBeNull();
});

it('tracks shipment with awaiting pickup status', function (): void {
    $tracking = $this->driver->track('ZONE-123ABC');

    expect($tracking->trackingNumber)->toBe('ZONE-123ABC');
    expect($tracking->status)->toBe(TrackingStatus::AwaitingPickup);
    expect($tracking->carrier)->toBe('zone');
    expect($tracking->events)->toHaveCount(1);
    expect($tracking->events->first()->code)->toBe('ZONE');
});

it('applies rate calculation types correctly', function (): void {
    $zone = ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'Malaysia Zone',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'method_code' => 'per_kg',
        'name' => 'Per KG Rate',
        'calculation_type' => 'per_kg',
        'base_rate' => 500,
        'per_unit_rate' => 200,
        'active' => true,
    ]);

    $packages = [new PackageData(weight: 3500)];

    $rates = $this->driver->getRates(
        $this->origin,
        $this->destination,
        $packages,
        ['owner_id' => 'test-owner-123', 'owner_type' => 'TestOwner'],
    );

    expect($rates)->toHaveCount(1);
    expect($rates->first()->rate)->toBe(1100); // 500 + (200 * 3) for ceil(3.5kg)
});

it('includes rate description as note', function (): void {
    $zone = ShippingZone::create([
        'owner_type' => 'TestOwner',
        'owner_id' => 'test-owner-123',
        'name' => 'Malaysia Zone',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'active' => true,
    ]);

    ShippingRate::create([
        'zone_id' => $zone->id,
        'method_code' => 'standard',
        'name' => 'Standard Delivery',
        'description' => 'Delivery within 3-5 business days',
        'calculation_type' => 'flat',
        'base_rate' => 800,
        'active' => true,
    ]);

    $packages = [new PackageData(weight: 1000)];

    $rates = $this->driver->getRates(
        $this->origin,
        $this->destination,
        $packages,
        ['owner_id' => 'test-owner-123', 'owner_type' => 'TestOwner'],
    );

    expect($rates->first()->note)->toBe('Delivery within 3-5 business days');
});
