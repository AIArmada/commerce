<?php

declare(strict_types=1);

use AIArmada\Tax\Data\TaxResultData;
use AIArmada\Tax\Exceptions\TaxZoneNotFoundException;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Services\TaxCalculator;
use AIArmada\Tax\Settings\TaxSettings;

describe('TaxCalculator', function (): void {
    beforeEach(function (): void {
        $this->calculator = $this->app->make(TaxCalculator::class);
    });

    it('calculate tax with explicit zone', function (): void {
        $zone = TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Standard Rate',
            'rate' => 600, // 6%
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id);

        $this->assertInstanceOf(TaxResultData::class, $result);
        $this->assertEquals(600, $result->taxAmount); // 6% of 10000 cents = 600 cents
        $this->assertEquals($zone->id, $result->zoneId);
        $this->assertFalse($result->includedInPrice);
    });

    it('calculate tax with address resolution', function (): void {
        $zone = TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY'],
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Standard Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $context = [
            'shipping_address' => [
                'country' => 'MY',
                'state' => 'Selangor',
                'postcode' => '43000',
            ],
        ];

        $result = $this->calculator->calculateTax(10000, 'standard', null, $context);

        $this->assertEquals(600, $result->taxAmount);
        $this->assertEquals($zone->id, $result->zoneId);
    });

    it('calculate tax with default zone fallback', function (): void {
        $zone = TaxZone::create([
            'name' => 'Default Zone',
            'code' => 'DEFAULT',
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Default Rate',
            'rate' => 1000, // 10%
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        // No zone specified and no address
        $result = $this->calculator->calculateTax(20000, 'standard');

        $this->assertEquals(2000, $result->taxAmount); // 10% of 20000
        $this->assertEquals($zone->id, $result->zoneId);
    });

    it('calculate tax with zero rate fallback', function (): void {
        config(['tax.features.zone_resolution.unknown_zone_behavior' => 'zero']);

        // No zones or rates configured
        $result = $this->calculator->calculateTax(10000, 'standard');

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('Zero Rate Zone', $result->zoneName);
    });

    it('calculate tax with tax inclusive pricing', function (): void {
        $this->app->bind(TaxSettings::class, fn () => throw new Exception('Use static tax configuration.'));

        config(['tax.defaults.prices_include_tax' => true]);

        $zone = TaxZone::create([
            'name' => 'Inclusive Zone',
            'code' => 'INCL',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Inclusive Rate',
            'rate' => 1000, // 10%
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(11000, 'standard', $zone->id);

        $this->assertEquals(1000, $result->taxAmount); // Extract 10% from 11000
        $this->assertTrue($result->includedInPrice);
    });

    it('calculate tax with rounding', function (): void {
        config(['tax.defaults.round_at_subtotal' => true]);

        $zone = TaxZone::create([
            'name' => 'Rounding Zone',
            'code' => 'ROUND',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Rounding Rate',
            'rate' => 875, // 8.75%
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id);

        // 10000 * 0.0875 = 875, rounded to 875
        $this->assertEquals(875, $result->taxAmount);
    });

    it('calculate tax with exemption', function (): void {
        $zone = TaxZone::create([
            'name' => 'Exempt Zone',
            'code' => 'EXEMPT',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Normal Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $customerId = '550e8400-e29b-41d4-a716-446655440123';

        TaxExemption::create([
            'exemptable_id' => $customerId,
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Non-profit',
            'status' => 'approved',
        ]);

        $context = ['customer_id' => $customerId];

        // Debug: check what exemptions exist
        $exemptions = TaxExemption::all();
        $this->assertCount(1, $exemptions);
        $exemption = $exemptions->first();
        $this->assertNull($exemption->tax_zone_id, 'tax_zone_id should be null');

        // Debug: check if the query finds the exemption
        $found = TaxExemption::query()
            ->where('exemptable_id', $customerId)
            ->where('status', 'approved')
            ->first();
        $this->assertNotNull($found, 'Exemption should be found by basic query');

        // Test forZone separately
        $foundWithZone = TaxExemption::query()
            ->where('exemptable_id', $customerId)
            ->where('status', 'approved')
            ->forZone($zone->id)
            ->first();
        $this->assertNotNull($foundWithZone, 'Exemption should be found by forZone query');

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id, $context);

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('Non-profit', $result->exemptionReason);
        $this->assertTrue($result->isExempt());
    });

    it('calculate tax with zone specific exemption', function (): void {
        $zone1 = TaxZone::create(['name' => 'Zone 1', 'code' => 'Z1', 'is_active' => true]);
        $zone2 = TaxZone::create(['name' => 'Zone 2', 'code' => 'Z2', 'is_active' => true]);

        TaxRate::create([
            'zone_id' => $zone1->id,
            'name' => 'Rate 1',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone2->id,
            'name' => 'Rate 2',
            'rate' => 800,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        // Exemption only for zone 1
        TaxExemption::create([
            'exemptable_id' => 'customer-123',
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => $zone1->id,
            'reason' => 'Zone specific exemption',
            'status' => 'approved',
        ]);

        $context = ['customer_id' => 'customer-123'];

        // Should be exempt in zone 1
        $result1 = $this->calculator->calculateTax(10000, 'standard', $zone1->id, $context);
        $this->assertEquals(0, $result1->taxAmount);

        // Should NOT be exempt in zone 2
        $result2 = $this->calculator->calculateTax(10000, 'standard', $zone2->id, $context);
        $this->assertEquals(800, $result2->taxAmount);
    });

    it('calculate shipping tax enabled', function (): void {
        $this->app->bind(TaxSettings::class, fn () => throw new Exception('Use static tax configuration.'));

        config(['tax.defaults.calculate_tax_on_shipping' => true]);

        $zone = TaxZone::create([
            'name' => 'Shipping Zone',
            'code' => 'SHIP',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Shipping Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateShippingTax(5000, $zone->id);

        $this->assertEquals(300, $result->taxAmount); // 6% of 5000
    });

    it('calculate shipping tax disabled', function (): void {
        $this->app->bind(TaxSettings::class, fn () => throw new Exception('Use static tax configuration.'));

        config(['tax.defaults.calculate_tax_on_shipping' => false]);

        $result = $this->calculator->calculateShippingTax(5000);

        $this->assertEquals(0, $result->taxAmount);
    });

    it('calculate tax with different tax classes', function (): void {
        $zone = TaxZone::create([
            'name' => 'Class Zone',
            'code' => 'CLASS',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Standard Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Reduced Rate',
            'rate' => 300,
            'tax_class' => 'reduced',
            'priority' => 10, // Higher priority
            'is_active' => true,
        ]);

        $standardResult = $this->calculator->calculateTax(10000, 'standard', $zone->id);
        $reducedResult = $this->calculator->calculateTax(10000, 'reduced', $zone->id);

        $this->assertEquals(600, $standardResult->taxAmount);
        $this->assertEquals(300, $reducedResult->taxAmount);
    });

    it('calculate tax with rate priority', function (): void {
        $zone = TaxZone::create([
            'name' => 'Priority Zone',
            'code' => 'PRIO',
            'is_active' => true,
        ]);

        // Lower priority rate
        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Low Priority',
            'rate' => 600,
            'tax_class' => 'standard',
            'priority' => 1,
            'is_active' => true,
        ]);

        // Higher priority rate
        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'High Priority',
            'rate' => 800,
            'tax_class' => 'standard',
            'priority' => 10,
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id);

        // Two non-compound rates can both apply: 600 + 800 = 1400.
        $this->assertEquals(1400, $result->taxAmount);
        $this->assertFalse($result->hasCompoundTaxes());
    });

    it('calculate tax with unknown zone error behavior', function (): void {
        config(['tax.features.zone_resolution.unknown_zone_behavior' => 'error']);

        // No zones configured, should throw error
        $this->calculator->calculateTax(10000, 'standard');
    })->throws(TaxZoneNotFoundException::class);

    it('tax disabled does not throw when unknown zone behavior is error', function (): void {
        $this->app->bind(TaxSettings::class, fn () => throw new Exception('Use static tax configuration.'));

        config(['tax.features.enabled' => false]);
        config(['tax.features.zone_resolution.unknown_zone_behavior' => 'error']);

        $result = $this->calculator->calculateTax(10000, 'standard');

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('Zero Rate Zone', $result->zoneName);
    });

    it('calculate tax with unknown zone zero behavior', function (): void {
        config(['tax.features.zone_resolution.unknown_zone_behavior' => 'zero']);

        $result = $this->calculator->calculateTax(10000, 'standard');

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('Zero Rate Zone', $result->zoneName);
    });

    it('calculate tax with address priority', function (): void {
        config(['tax.features.zone_resolution.address_priority' => 'billing']);

        $zone = TaxZone::create([
            'name' => 'Billing Priority',
            'code' => 'BILL',
            'countries' => ['US'],
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Billing Rate',
            'rate' => 700,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $context = [
            'shipping_address' => ['country' => 'MY'],
            'billing_address' => ['country' => 'US'],
        ];

        $result = $this->calculator->calculateTax(10000, 'standard', null, $context);

        // Should use billing address (US) over shipping (MY)
        $this->assertEquals(700, $result->taxAmount);
    });

    it('calculate tax with disabled exemptions', function (): void {
        config(['tax.features.exemptions.enabled' => false]);

        $zone = TaxZone::create([
            'name' => 'Disabled Exemptions',
            'code' => 'DISABLED',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Normal Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        TaxExemption::create([
            'exemptable_id' => 'customer-123',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Should be ignored',
            'status' => 'approved',
        ]);

        $context = ['customer_id' => 'customer-123'];
        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id, $context);

        // Exemption should be ignored, tax should be calculated
        $this->assertEquals(600, $result->taxAmount);
        $this->assertNull($result->exemptionReason);
    });
});
