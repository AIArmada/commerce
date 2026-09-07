<?php

declare(strict_types=1);

use AIArmada\Tax\Exceptions\TaxZoneNotFoundException;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Services\TaxCalculator;
use AIArmada\Tax\Settings\TaxSettings;
use AIArmada\Tax\Settings\TaxZoneSettings;

describe('TaxCalculatorEdgeCases', function (): void {
    beforeEach(function (): void {
        $this->calculator = $this->app->make(TaxCalculator::class);
    });

    afterEach(function (): void {
        Mockery::close();
    });

    it('compound tax with tax inclusive pricing', function (): void {
        $mockSettings = Mockery::mock(TaxSettings::class);
        $mockSettings->enabled = true;
        $mockSettings->pricesIncludeTax = true;
        $mockSettings->shippingTaxable = true;
        $mockSettings->taxBasedOnShippingAddress = true;

        $this->app->instance(TaxSettings::class, $mockSettings);

        $zone = TaxZone::create([
            'name' => 'Compound Inclusive Zone',
            'code' => 'COMP-INCL',
            'is_active' => true,
        ]);

        // Non-compound rate (5%)
        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Base Rate',
            'rate' => 500,
            'tax_class' => 'standard',
            'is_compound' => false,
            'priority' => 10,
            'is_active' => true,
        ]);

        // Compound rate (3%)
        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Compound Rate',
            'rate' => 300,
            'tax_class' => 'standard',
            'is_compound' => true,
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id);

        $this->assertTrue($result->includedInPrice);
        $this->assertTrue($result->hasCompoundTaxes());
        $this->assertCount(2, $result->breakdown);

        $compoundBreakdown = collect($result->breakdown)->firstWhere('is_compound', true);
        $this->assertNotNull($compoundBreakdown);
        $this->assertEquals('Compound Rate', $compoundBreakdown['name']);
    });

    it('compound tax with tax exclusive pricing', function (): void {
        config(['tax.defaults.prices_include_tax' => false]);

        $zone = TaxZone::create([
            'name' => 'Compound Exclusive Zone',
            'code' => 'COMP-EXCL',
            'is_active' => true,
        ]);

        // Non-compound rate (5%)
        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Federal Tax',
            'rate' => 500,
            'tax_class' => 'standard',
            'is_compound' => false,
            'priority' => 10,
            'is_active' => true,
        ]);

        // Compound rate (3%) - applied on base + federal
        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'State Tax',
            'rate' => 300,
            'tax_class' => 'standard',
            'is_compound' => true,
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id);

        // Base: 10000
        // Federal: 10000 * 5% = 500
        // State (compound): (10000 + 500) * 3% = 315
        // Total: 500 + 315 = 815
        $this->assertEquals(815, $result->taxAmount);
        $this->assertFalse($result->includedInPrice);
        $this->assertTrue($result->hasCompoundTaxes());
    });

    it('fallback zone id resolution', function (): void {
        $fallbackZone = TaxZone::create([
            'name' => 'Fallback Zone',
            'code' => 'FALLBACK',
            'is_default' => false,
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $fallbackZone->id,
            'name' => 'Fallback Rate',
            'rate' => 500,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        config(['tax.features.zone_resolution.fallback_zone_id' => $fallbackZone->id]);

        $result = $this->calculator->calculateTax(10000, 'standard');

        $this->assertEquals(500, $result->taxAmount);
        $this->assertEquals($fallbackZone->id, $result->zoneId);
    });

    it('fallback zone id not found', function (): void {
        config(['tax.features.zone_resolution.fallback_zone_id' => 'non-existent-uuid']);
        config(['tax.features.zone_resolution.unknown_zone_behavior' => 'zero']);

        $result = $this->calculator->calculateTax(10000, 'standard');

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('Zero Rate Zone', $result->zoneName);
    });

    it('unknown zone behavior default branch', function (): void {
        config(['tax.features.zone_resolution.unknown_zone_behavior' => 'unknown_value']);

        $result = $this->calculator->calculateTax(10000, 'standard');

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('Zero Rate Zone', $result->zoneName);
    });

    it('create exempt result with zone id', function (): void {
        $zone = TaxZone::create([
            'name' => 'Exempt Zone',
            'code' => 'EXEMPT',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Standard Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        TaxExemption::create([
            'exemptable_id' => 'customer-exempt-test',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Test exemption',
            'status' => 'approved',
        ]);

        $result = $this->calculator->calculateTax(
            10000,
            'standard',
            $zone->id,
            ['customer_id' => 'customer-exempt-test']
        );

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals($zone->id, $result->zoneId);
        $this->assertEquals($zone->name, $result->zoneName);
        $this->assertEquals('Test exemption', $result->exemptionReason);
    });

    it('create exempt result without zone id', function (): void {
        TaxExemption::create([
            'exemptable_id' => 'customer-no-zone',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Test exemption no zone',
            'status' => 'approved',
        ]);

        $result = $this->calculator->calculateTax(
            10000,
            'standard',
            null,
            ['customer_id' => 'customer-no-zone']
        );

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('Zero Rate Zone', $result->zoneName);
        $this->assertEquals('Test exemption no zone', $result->exemptionReason);
    });

    it('create zero result with valid zone id', function (): void {
        config(['tax.features.enabled' => false]);

        $zone = TaxZone::create([
            'name' => 'Test Zone',
            'code' => 'TEST',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id);

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals($zone->id, $result->zoneId);
        $this->assertEquals($zone->name, $result->zoneName);
    });

    it('create zero result with invalid zone id', function (): void {
        config(['tax.features.enabled' => false]);

        $result = $this->calculator->calculateTax(10000, 'standard', 'invalid-uuid');

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('Zero Rate Zone', $result->zoneName);
    });

    it('settings fallback when settings throw', function (): void {
        $this->app->bind(TaxSettings::class, fn () => throw new Exception('Settings not configured'));

        config(['tax.features.enabled' => true]);
        config(['tax.defaults.prices_include_tax' => false]);
        config(['tax.defaults.calculate_tax_on_shipping' => true]);

        $zone = TaxZone::create([
            'name' => 'Config Fallback Zone',
            'code' => 'CFG',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Config Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id);

        $this->assertEquals(600, $result->taxAmount);
        $this->assertFalse($result->includedInPrice);
    });

    it('zone settings fallback when settings throw', function (): void {
        $this->app->bind(TaxZoneSettings::class, fn () => throw new Exception('Zone settings not configured'));

        config(['tax.features.zone_resolution.use_customer_address' => true]);
        config(['tax.features.zone_resolution.unknown_zone_behavior' => 'zero']);
        config(['tax.features.zone_resolution.fallback_zone_id' => null]);

        $zone = TaxZone::create([
            'name' => 'Address Zone',
            'code' => 'ADDR',
            'countries' => ['MY'],
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Address Rate',
            'rate' => 700,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', null, [
            'shipping_address' => ['country' => 'MY'],
        ]);

        $this->assertEquals(700, $result->taxAmount);
    });

    it('tax settings enabled via spatie settings', function (): void {
        $mockSettings = Mockery::mock(TaxSettings::class);
        $mockSettings->enabled = false;
        $mockSettings->pricesIncludeTax = false;
        $mockSettings->shippingTaxable = true;
        $mockSettings->taxBasedOnShippingAddress = true;

        $this->app->instance(TaxSettings::class, $mockSettings);

        $zone = TaxZone::create([
            'name' => 'Disabled Zone',
            'code' => 'DIS',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Disabled Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id);

        $this->assertEquals(0, $result->taxAmount);
    });

    it('prices include tax via spatie settings', function (): void {
        $mockSettings = Mockery::mock(TaxSettings::class);
        $mockSettings->enabled = true;
        $mockSettings->pricesIncludeTax = true;
        $mockSettings->shippingTaxable = true;
        $mockSettings->taxBasedOnShippingAddress = true;

        $this->app->instance(TaxSettings::class, $mockSettings);

        $zone = TaxZone::create([
            'name' => 'Inclusive Zone',
            'code' => 'INCL',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Inclusive Rate',
            'rate' => 1000,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(11000, 'standard', $zone->id);

        $this->assertEquals(1000, $result->taxAmount);
        $this->assertTrue($result->includedInPrice);
    });

    it('shipping not taxable via spatie settings', function (): void {
        $mockSettings = Mockery::mock(TaxSettings::class);
        $mockSettings->enabled = true;
        $mockSettings->pricesIncludeTax = false;
        $mockSettings->shippingTaxable = false;
        $mockSettings->taxBasedOnShippingAddress = true;

        $this->app->instance(TaxSettings::class, $mockSettings);

        $result = $this->calculator->calculateShippingTax(5000);

        $this->assertEquals(0, $result->taxAmount);
    });

    it('zone resolution via spatie settings', function (): void {
        $mockZoneSettings = Mockery::mock(TaxZoneSettings::class);
        $mockZoneSettings->autoDetectZone = true;
        $mockZoneSettings->fallbackBehavior = 'zero';
        $mockZoneSettings->defaultZoneId = null;

        $this->app->instance(TaxZoneSettings::class, $mockZoneSettings);

        $zone = TaxZone::create([
            'name' => 'Auto Zone',
            'code' => 'AUTO',
            'countries' => ['SG'],
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Auto Rate',
            'rate' => 800,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', null, [
            'shipping_address' => ['country' => 'SG'],
        ]);

        $this->assertEquals(800, $result->taxAmount);
    });

    it('zone resolution disabled via config', function (): void {
        config(['tax.features.zone_resolution.use_customer_address' => false]);

        $zone = TaxZone::create([
            'name' => 'Manual Zone',
            'code' => 'MAN',
            'countries' => ['MY'],
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Manual Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', null, [
            'shipping_address' => ['country' => 'MY'],
        ]);

        $this->assertEquals(0, $result->taxAmount);
    });

    it('fallback zone via config', function (): void {
        $fallbackZone = TaxZone::create([
            'name' => 'Fallback',
            'code' => 'FALLBACK',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $fallbackZone->id,
            'name' => 'Fallback Rate',
            'rate' => 550,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        config(['tax.features.zone_resolution.use_customer_address' => false]);
        config(['tax.features.zone_resolution.fallback_zone_id' => $fallbackZone->id]);

        $result = $this->calculator->calculateTax(10000, 'standard');

        $this->assertEquals(550, $result->taxAmount);
        $this->assertEquals($fallbackZone->id, $result->zoneId);
    });

    it('billing address priority via config', function (): void {
        config(['tax.features.zone_resolution.address_priority' => 'billing']);

        $sgZone = TaxZone::create([
            'name' => 'Singapore',
            'code' => 'SG',
            'countries' => ['SG'],
            'is_active' => true,
        ]);

        $myZone = TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY'],
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $sgZone->id,
            'name' => 'SG Rate',
            'rate' => 900,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $myZone->id,
            'name' => 'MY Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', null, [
            'shipping_address' => ['country' => 'MY'],
            'billing_address' => ['country' => 'SG'],
        ]);

        $this->assertEquals(900, $result->taxAmount);
    });

    it('unknown zone behavior error via config', function (): void {
        config(['tax.features.zone_resolution.use_customer_address' => false]);
        config(['tax.features.zone_resolution.unknown_zone_behavior' => 'error']);

        $this->calculator->calculateTax(10000, 'standard');
    })->throws(TaxZoneNotFoundException::class);
});
