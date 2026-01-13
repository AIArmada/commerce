<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Facades;

use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Data\TaxResultData;
use AIArmada\Tax\Facades\Tax;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Services\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaxFacadeTest extends TaxTestCase
{
    use RefreshDatabase;

    public function test_facade_resolves_to_tax_calculator(): void
    {
        $resolved = Tax::getFacadeRoot();

        $this->assertInstanceOf(TaxCalculatorInterface::class, $resolved);
        $this->assertInstanceOf(TaxCalculator::class, $resolved);
    }

    public function test_facade_can_calculate_tax(): void
    {
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
    }

    public function test_facade_can_calculate_shipping_tax(): void
    {
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
    }

    public function test_facade_returns_zero_when_tax_disabled(): void
    {
        config(['tax.features.enabled' => false]);

        $result = Tax::calculateTax(10000);

        $this->assertEquals(0, $result->taxAmount);
    }

    public function test_facade_is_singleton(): void
    {
        $instance1 = Tax::getFacadeRoot();
        $instance2 = Tax::getFacadeRoot();

        $this->assertSame($instance1, $instance2);
    }

    public function test_can_resolve_via_app_helper(): void
    {
        $viaTax = app('tax');
        $viaInterface = app(TaxCalculatorInterface::class);

        $this->assertInstanceOf(TaxCalculator::class, $viaTax);
        $this->assertSame($viaTax, $viaInterface);
    }

    public function test_facade_with_context(): void
    {
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
    }
}
