<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Models\ShippingRate;
use AIArmada\Shipping\Models\ShippingZone;

describe('ShippingRate Model', function (): void {
    it('can create a shipping rate with required fields', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Standard Shipping',
            'calculation_type' => 'flat',
            'base_rate' => 500,
        ]);

        expect($rate)->toBeInstanceOf(ShippingRate::class);
        expect($rate->zone_id)->toBe($zone->id);
        expect($rate->carrier_code)->toBe('test-carrier');
        expect($rate->method_code)->toBe('standard');
        expect($rate->name)->toBe('Standard Shipping');
        expect($rate->calculation_type)->toBe('flat');
        expect($rate->base_rate)->toBe(500);
        expect($rate->active)->toBeTrue();
    });

    it('casts attributes correctly', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Test Rate',
            'calculation_type' => 'flat',
            'base_rate' => '1000',
            'per_unit_rate' => '50',
            'min_charge' => '200',
            'max_charge' => '5000',
            'free_shipping_threshold' => '10000',
            'estimated_days_min' => '2',
            'estimated_days_max' => '5',
            'rate_table' => [['min_weight' => 0, 'max_weight' => 1000, 'rate' => 500]],
            'conditions' => ['category' => 'electronics'],
            'active' => false,
        ]);

        expect($rate->base_rate)->toBeInt();
        expect($rate->per_unit_rate)->toBeInt();
        expect($rate->min_charge)->toBeInt();
        expect($rate->max_charge)->toBeInt();
        expect($rate->free_shipping_threshold)->toBeInt();
        expect($rate->estimated_days_min)->toBeInt();
        expect($rate->estimated_days_max)->toBeInt();
        expect($rate->rate_table)->toBeArray();
        expect($rate->conditions)->toBeArray();
        expect($rate->active)->toBeBool();
    });

    it('has correct relationships', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Test Rate',
            'calculation_type' => 'flat',
            'base_rate' => 500,
        ]);

        expect($rate->zone())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
        expect($rate->zone->name)->toBe('Test Zone');
    });

    it('has active scope', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Active Rate',
            'calculation_type' => 'flat',
            'base_rate' => 500,
            'active' => true,
        ]);

        ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'express',
            'name' => 'Inactive Rate',
            'calculation_type' => 'flat',
            'base_rate' => 1000,
            'active' => false,
        ]);

        $activeRates = ShippingRate::active()->get();
        expect($activeRates)->toHaveCount(1);
        expect($activeRates->first()->name)->toBe('Active Rate');
    });

    it('has forCarrier scope', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'fedex',
            'method_code' => 'standard',
            'name' => 'FedEx Rate',
            'calculation_type' => 'flat',
            'base_rate' => 500,
        ]);

        ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => null, // Generic rate
            'method_code' => 'standard',
            'name' => 'Generic Rate',
            'calculation_type' => 'flat',
            'base_rate' => 300,
        ]);

        $fedexRates = ShippingRate::forCarrier('fedex')->get();
        expect($fedexRates)->toHaveCount(2); // Both FedEx and generic

        $upsRates = ShippingRate::forCarrier('ups')->get();
        expect($upsRates)->toHaveCount(1); // Only generic
        expect($upsRates->first()->name)->toBe('Generic Rate');
    });

    it('calculates flat rate correctly', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Flat Rate',
            'calculation_type' => 'flat',
            'base_rate' => 750,
        ]);

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
        ];

        $calculated = $rate->calculateRate($packages);
        expect($calculated)->toBe(750);
    });

    it('calculates per kg rate correctly', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Per Kg Rate',
            'calculation_type' => 'per_kg',
            'base_rate' => 500, // Base for first kg
            'per_unit_rate' => 200, // Per additional kg
        ]);

        $packages = [
            new PackageData(3500, 10, 5, 5, 1000, 'box', 1), // 3.5kg
        ];

        $calculated = $rate->calculateRate($packages);
        expect($calculated)->toBe(1100); // 500 + (200 * 3) for 4kg total (ceil(3.5))
    });

    it('applies free shipping threshold', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Free Shipping Rate',
            'calculation_type' => 'flat',
            'base_rate' => 750,
            'free_shipping_threshold' => 5000,
        ]);

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
        ];

        $normalRate = $rate->calculateRate($packages, 4000);
        expect($normalRate)->toBe(750);

        $freeRate = $rate->calculateRate($packages, 6000);
        expect($freeRate)->toBe(0);
    });

    it('applies min and max charge constraints', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Constrained Rate',
            'calculation_type' => 'percentage',
            'per_unit_rate' => 1000, // 10%
            'min_charge' => 200,
            'max_charge' => 1000,
        ]);

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
        ];

        $lowCartRate = $rate->calculateRate($packages, 1000); // 10% = 100, but min 200
        expect($lowCartRate)->toBe(200);

        $highCartRate = $rate->calculateRate($packages, 20000); // 10% = 2000, but max 1000
        expect($highCartRate)->toBe(1000);
    });

    it('provides delivery estimate', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $singleDayRate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'express',
            'name' => 'Express',
            'calculation_type' => 'flat',
            'base_rate' => 1500,
            'estimated_days_min' => 1,
            'estimated_days_max' => 1,
        ]);

        $rangeRate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Standard',
            'calculation_type' => 'flat',
            'base_rate' => 750,
            'estimated_days_min' => 3,
            'estimated_days_max' => 7,
        ]);

        $noEstimateRate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'ground',
            'name' => 'Ground',
            'calculation_type' => 'flat',
            'base_rate' => 500,
        ]);

        expect($singleDayRate->getDeliveryEstimate())->toBe('1 day');
        expect($rangeRate->getDeliveryEstimate())->toBe('3-7 days');
        expect($noEstimateRate->getDeliveryEstimate())->toBeNull();
    });

    it('calculates table rate correctly for weight tiers', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Table Rate',
            'calculation_type' => 'table',
            'base_rate' => 500, // Fallback rate
            'rate_table' => [
                ['min_weight' => 0, 'max_weight' => 1000, 'rate' => 300],
                ['min_weight' => 1001, 'max_weight' => 5000, 'rate' => 600],
                ['min_weight' => 5001, 'max_weight' => 10000, 'rate' => 900],
            ],
        ]);

        $packages = [
            new PackageData(500, 10, 5, 5, 250, 'box', 1), // 500g - first tier
        ];
        expect($rate->calculateRate($packages))->toBe(300);

        $mediumPackages = [
            new PackageData(3000, 10, 5, 5, 500, 'box', 1), // 3kg - second tier
        ];
        expect($rate->calculateRate($mediumPackages))->toBe(600);

        $heavyPackages = [
            new PackageData(8000, 10, 5, 5, 1000, 'box', 1), // 8kg - third tier
        ];
        expect($rate->calculateRate($heavyPackages))->toBe(900);
    });

    it('returns last tier rate for weight exceeding all tiers', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Table Rate',
            'calculation_type' => 'table',
            'base_rate' => 500,
            'rate_table' => [
                ['min_weight' => 0, 'max_weight' => 1000, 'rate' => 300],
                ['min_weight' => 1001, 'max_weight' => 5000, 'rate' => 600],
            ],
        ]);

        $packages = [
            new PackageData(50000, 30, 30, 30, 2000, 'box', 1), // 50kg - exceeds all tiers
        ];

        $calculated = $rate->calculateRate($packages);
        expect($calculated)->toBe(600); // Last tier rate
    });

    it('returns base rate for empty rate table', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Table Rate',
            'calculation_type' => 'table',
            'base_rate' => 500,
            'rate_table' => [],
        ]);

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
        ];

        $calculated = $rate->calculateRate($packages);
        expect($calculated)->toBe(500); // Falls back to base_rate
    });

    it('returns base rate for null rate table', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Table Rate',
            'calculation_type' => 'table',
            'base_rate' => 500,
            'rate_table' => null,
        ]);

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
        ];

        $calculated = $rate->calculateRate($packages);
        expect($calculated)->toBe(500); // Falls back to base_rate
    });

    it('handles table rate tier without rate key', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Table Rate',
            'calculation_type' => 'table',
            'base_rate' => 500,
            'rate_table' => [
                ['min_weight' => 0, 'max_weight' => 10000], // Missing 'rate' key
            ],
        ]);

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
        ];

        $calculated = $rate->calculateRate($packages);
        expect($calculated)->toBe(500); // Falls back to base_rate when tier has no rate
    });

    it('calculates per item rate correctly', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Per Item Rate',
            'calculation_type' => 'per_item',
            'base_rate' => 300, // Base for first item
            'per_unit_rate' => 100, // Per additional item
        ]);

        $packages = [
            new PackageData(500, 10, 5, 5, 250, 'box', 3), // 3 items
        ];

        $calculated = $rate->calculateRate($packages);
        expect($calculated)->toBe(500); // 300 + (100 * 2) for 3 items
    });

    it('handles unknown calculation type with base rate fallback', function (): void {
        $zone = ShippingZone::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'name' => 'Test Zone',
            'code' => 'TEST_ZONE',
            'type' => 'country',
            'countries' => ['US'],
        ]);

        $rate = ShippingRate::create([
            'zone_id' => $zone->id,
            'carrier_code' => 'test-carrier',
            'method_code' => 'standard',
            'name' => 'Unknown Type Rate',
            'calculation_type' => 'unknown_type',
            'base_rate' => 777,
        ]);

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
        ];

        $calculated = $rate->calculateRate($packages);
        expect($calculated)->toBe(777); // Falls back to base_rate for unknown type
    });
});