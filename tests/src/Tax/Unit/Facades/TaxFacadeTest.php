<?php

declare(strict_types=1);

use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Data\TaxResultData;
use AIArmada\Tax\Facades\Tax;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Services\TaxCalculator;
use AIArmada\Tax\Settings\TaxSettings;

describe('TaxFacade', function (): void {
    it('facade resolves to tax calculator', function (): void {
        $resolved = Tax::getFacadeRoot();

        $this->assertInstanceOf(TaxCalculatorInterface::class, $resolved);
        $this->assertInstanceOf(TaxCalculator::class, $resolved);
    });

    it('facade can calculate tax', function (): void {
        $zone = TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'is_active' => true,
            'is_default' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'SST',
            'rate' => 600, // 6%
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = Tax::calculateTax(10000, 'standard', $zone->id);

        $this->assertInstanceOf(TaxResultData::class, $result);
        $this->assertEquals(600, $result->taxAmount);
        $this->assertEquals('SST', $result->rateName);
        $this->assertEquals($zone->id, $result->zoneId);
    });

    it('facade can calculate shipping tax', function (): void {
        $this->app->bind(TaxSettings::class, fn () => throw new Exception('Use static tax configuration.'));

        config(['tax.defaults.calculate_tax_on_shipping' => true]);

        $zone = TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'is_active' => true,
            'is_default' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'SST',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_shipping' => true,
            'is_active' => true,
        ]);

        $result = Tax::calculateShippingTax(5000, $zone->id);

        $this->assertInstanceOf(TaxResultData::class, $result);
        $this->assertEquals(300, $result->taxAmount); // 6% of 5000
    });

    it('facade returns zero when tax disabled', function (): void {
        $this->app->bind(TaxSettings::class, fn () => throw new Exception('Use static tax configuration.'));

        config(['tax.features.enabled' => false]);

        $result = Tax::calculateTax(10000);

        $this->assertEquals(0, $result->taxAmount);
    });

    it('facade is singleton', function (): void {
        $instance1 = Tax::getFacadeRoot();
        $instance2 = Tax::getFacadeRoot();

        $this->assertSame($instance1, $instance2);
    });

    it('can resolve via app helper', function (): void {
        $viaTax = app('tax');
        $viaInterface = app(TaxCalculatorInterface::class);

        $this->assertInstanceOf(TaxCalculator::class, $viaTax);
        $this->assertSame($viaTax, $viaInterface);
    });

    it('facade with context', function (): void {
        $zone = TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY'],
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'SST',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $context = [
            'shipping_address' => [
                'country' => 'MY',
                'state' => 'Selangor',
            ],
        ];

        $result = Tax::calculateTax(10000, 'standard', null, $context);

        $this->assertEquals(600, $result->taxAmount);
        $this->assertEquals($zone->id, $result->zoneId);
    });
});
