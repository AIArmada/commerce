<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Data;

use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\Tax\Data\TaxResultData;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaxResultDataTest extends TaxTestCase
{
    use RefreshDatabase;

    public function test_can_create_tax_result(): void
    {
        $zone = TaxZone::create([
            'name' => 'Test Zone',
            'code' => 'TEST',
            'is_active' => true,
        ]);

        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Test Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = new TaxResultData(
            taxAmount: 600,
            rate: $rate,
            zone: $zone,
            includedInPrice: false,
            exemptionReason: null,
        );

        $this->assertEquals(600, $result->taxAmount);
        $this->assertSame($rate, $result->rate);
        $this->assertSame($zone, $result->zone);
        $this->assertFalse($result->includedInPrice);
        $this->assertNull($result->exemptionReason);
    }

    public function test_is_exempt_with_exemption_reason(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);
        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Rate',
            'rate' => 0,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $exemptResult = new TaxResultData(
            taxAmount: 0,
            rate: $rate,
            zone: $zone,
            includedInPrice: false,
            exemptionReason: 'Non-profit organization',
        );

        $this->assertTrue($exemptResult->isExempt());
    }

    public function test_is_exempt_with_zero_rate(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);
        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Zero Rate',
            'rate' => 0,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $zeroResult = new TaxResultData(
            taxAmount: 0,
            rate: $rate,
            zone: $zone,
            includedInPrice: false,
        );

        $this->assertTrue($zeroResult->isExempt());
    }

    public function test_is_exempt_with_normal_tax(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);
        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Normal Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $normalResult = new TaxResultData(
            taxAmount: 600,
            rate: $rate,
            zone: $zone,
            includedInPrice: false,
        );

        $this->assertFalse($normalResult->isExempt());
    }

    public function test_get_formatted_amount(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);
        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = new TaxResultData(
            taxAmount: 1234, // $12.34
            rate: $rate,
            zone: $zone,
        );

        $this->assertEquals('RM 12.34', $result->getFormattedAmount());
    }

    public function test_get_summary_with_exemption(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);
        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Rate',
            'rate' => 0,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $exemptResult = new TaxResultData(
            taxAmount: 0,
            rate: $rate,
            zone: $zone,
            exemptionReason: 'Tax Exempt Organization',
        );

        $this->assertEquals('Tax Exempt Organization', $exemptResult->getSummary());
    }

    public function test_get_summary_with_normal_tax(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);
        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'GST',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $normalResult = new TaxResultData(
            taxAmount: 600,
            rate: $rate,
            zone: $zone,
        );

        $this->assertEquals('GST (6.00%)', $normalResult->getSummary());
    }

    public function test_get_summary_with_zero_rate(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);
        $rate = TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Zero Rate',
            'rate' => 0,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $zeroResult = new TaxResultData(
            taxAmount: 0,
            rate: $rate,
            zone: $zone,
        );

        $this->assertEquals('Tax Exempt', $zeroResult->getSummary());
    }
}
