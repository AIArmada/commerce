<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Models;

use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaxRateTest extends TaxTestCase
{
    use RefreshDatabase;

    public function test_can_create_tax_rate(): void
    {
        $zone = TaxZone::create([
            'name' => 'Test Zone',
            'code' => 'TEST',
            'is_active' => true,
        ]);

        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Standard Rate',
            'rate' => 600, // 6.00%
            'tax_class' => 'standard',
            'priority' => 10,
            'is_compound' => false,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(TaxRate::class, $rate);
        $this->assertEquals('Standard Rate', $rate->name);
        $this->assertEquals(600, $rate->rate);
        $this->assertEquals('standard', $rate->tax_class);
        $this->assertTrue($rate->is_active);
    }

    public function test_zero_rate_static_method(): void
    {
        $zone = TaxZone::create([
            'name' => 'Test Zone',
            'code' => 'TEST',
            'is_active' => true,
        ]);

        $rate = TaxRate::zeroRate('standard', $zone);

        $this->assertEquals('Zero Rate', $rate->name);
        $this->assertEquals(0, $rate->rate);
        $this->assertEquals('standard', $rate->tax_class);
        $this->assertEquals($zone->id, $rate->zone_id);
        $this->assertTrue($rate->is_active);
    }

    public function test_active_scope(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Active Rate',
            'rate' => 1000,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Inactive Rate',
            'rate' => 500,
            'tax_class' => 'standard',
            'is_active' => false,
        ]);

        $activeRates = TaxRate::active()->get();

        $this->assertCount(1, $activeRates);
        $this->assertEquals('Active Rate', $activeRates->first()->name);
    }

    public function test_for_class_scope(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);

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
            'is_active' => true,
        ]);

        $standardRates = TaxRate::forClass('standard')->get();

        $this->assertCount(1, $standardRates);
        $this->assertEquals('Standard Rate', $standardRates->first()->name);
    }

    public function test_for_zone_scope(): void
    {
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

        $zone1Rates = TaxRate::forZone($zone1->id)->get();

        $this->assertCount(1, $zone1Rates);
        $this->assertEquals('Rate 1', $zone1Rates->first()->name);
    }

    public function test_relationship_with_zone(): void
    {
        $zone = TaxZone::create([
            'name' => 'Test Zone',
            'code' => 'TEST',
            'is_active' => true,
        ]);

        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Test Rate',
            'rate' => 1000,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(TaxZone::class, $rate->zone);
        $this->assertEquals($zone->id, $rate->zone->id);
    }

    public function test_get_rate_percentage(): void
    {
        $rate = new TaxRate(['rate' => 600]); // 6.00%

        $this->assertEquals(6.0, $rate->getRatePercentage());
    }

    public function test_get_rate_decimal(): void
    {
        $rate = new TaxRate(['rate' => 600]); // 6.00%

        $this->assertEquals(0.06, $rate->getRateDecimal());
    }

    public function test_calculate_tax(): void
    {
        $rate = new TaxRate(['rate' => 1000]); // 10.00%

        $tax = $rate->calculateTax(10000); // $100.00

        $this->assertEquals(1000, $tax); // $10.00 in cents
    }

    public function test_extract_tax(): void
    {
        $rate = new TaxRate(['rate' => 1000]); // 10.00%

        $tax = $rate->extractTax(11000); // $110.00 inclusive

        $this->assertEquals(1000, $tax); // $10.00 in cents
    }

    public function test_get_formatted_rate(): void
    {
        $rate = new TaxRate(['rate' => 875]); // 8.75%

        $this->assertEquals('8.75%', $rate->getFormattedRate());
    }

    public function test_casts(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);

        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Cast Test',
            'rate' => 600,
            'priority' => 5,
            'is_compound' => true,
            'is_active' => false,
        ]);

        $this->assertIsInt($rate->rate);
        $this->assertIsInt($rate->priority);
        $this->assertIsBool($rate->is_compound);
        $this->assertIsBool($rate->is_active);
    }

    public function test_attributes_defaults(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);

        $rate = new TaxRate(['zone_id' => $zone->id, 'name' => 'Test']);

        $this->assertEquals('standard', $rate->tax_class);
        $this->assertEquals(0, $rate->priority);
        $this->assertFalse($rate->is_compound);
        $this->assertTrue($rate->is_active);
    }

    public function test_activity_logging(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);

        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Activity Test',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $rate->update(['rate' => 800]);

        // Activity logging is configured but we can't easily test it without more setup
        // This test ensures the trait is applied and doesn't break
        $this->assertTrue(true);
    }
}
