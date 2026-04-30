<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Data;

use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\Tax\Data\TaxResultData;

class TaxResultDataTest extends TaxTestCase
{
    public function test_can_create_tax_result(): void
    {
        $result = new TaxResultData(
            taxAmount: 600,
            rateId: 'rate-123',
            rateName: 'Test Rate',
            ratePercentage: 600,
            zoneId: 'zone-123',
            zoneName: 'Test Zone',
            includedInPrice: false,
            exemptionReason: null,
        );

        $this->assertEquals(600, $result->taxAmount);
        $this->assertEquals('rate-123', $result->rateId);
        $this->assertEquals('Test Rate', $result->rateName);
        $this->assertEquals(600, $result->ratePercentage);
        $this->assertEquals('zone-123', $result->zoneId);
        $this->assertEquals('Test Zone', $result->zoneName);
        $this->assertFalse($result->includedInPrice);
        $this->assertNull($result->exemptionReason);
    }

    public function test_is_exempt_with_exemption_reason(): void
    {
        $exemptResult = new TaxResultData(
            taxAmount: 0,
            rateId: 'exempt',
            rateName: 'Exempt',
            ratePercentage: 0,
            zoneId: 'zone-123',
            zoneName: 'Zone',
            includedInPrice: false,
            exemptionReason: 'Non-profit organization',
        );

        $this->assertTrue($exemptResult->isExempt());
    }

    public function test_is_exempt_with_zero_rate(): void
    {
        // Zero-rate is NOT an exemption — it is a valid 0% tax rate.
        // Only a result with an exemptionReason set is truly exempt.
        $zeroResult = new TaxResultData(
            taxAmount: 0,
            rateId: 'zero',
            rateName: 'Zero Rate',
            ratePercentage: 0,
            zoneId: 'zone-123',
            zoneName: 'Zone',
            includedInPrice: false,
        );

        $this->assertFalse($zeroResult->isExempt());
    }

    public function test_is_exempt_with_normal_tax(): void
    {
        $normalResult = new TaxResultData(
            taxAmount: 600,
            rateId: 'rate-123',
            rateName: 'Normal Rate',
            ratePercentage: 600,
            zoneId: 'zone-123',
            zoneName: 'Zone',
            includedInPrice: false,
        );

        $this->assertFalse($normalResult->isExempt());
    }

    public function test_get_formatted_amount(): void
    {
        $result = new TaxResultData(
            taxAmount: 1234, // $12.34
            rateId: 'rate-123',
            rateName: 'Rate',
            ratePercentage: 600,
            zoneId: 'zone-123',
            zoneName: 'Zone',
        );

        $this->assertEquals('$ 12.34', $result->getFormattedAmount());
    }

    public function test_get_formatted_amount_with_custom_currency(): void
    {
        $result = new TaxResultData(
            taxAmount: 1234,
            rateId: 'rate-123',
            rateName: 'Rate',
            ratePercentage: 600,
            zoneId: 'zone-123',
            zoneName: 'Zone',
        );

        $this->assertEquals('USD 12.34', $result->getFormattedAmount('USD'));
    }

    public function test_get_formatted_rate(): void
    {
        $result = new TaxResultData(
            taxAmount: 600,
            rateId: 'rate-123',
            rateName: 'Rate',
            ratePercentage: 650, // 6.50%
            zoneId: 'zone-123',
            zoneName: 'Zone',
        );

        $this->assertEquals('6.50%', $result->getFormattedRate());
    }

    public function test_get_summary_with_exemption(): void
    {
        $exemptResult = new TaxResultData(
            taxAmount: 0,
            rateId: 'exempt',
            rateName: 'Exempt',
            ratePercentage: 0,
            zoneId: 'zone-123',
            zoneName: 'Zone',
            exemptionReason: 'Tax Exempt Organization',
        );

        $this->assertEquals('Tax Exempt Organization', $exemptResult->getSummary());
    }

    public function test_get_summary_with_normal_tax(): void
    {
        $normalResult = new TaxResultData(
            taxAmount: 600,
            rateId: 'rate-123',
            rateName: 'GST',
            ratePercentage: 600,
            zoneId: 'zone-123',
            zoneName: 'Zone',
        );

        $this->assertEquals('GST (6.00%)', $normalResult->getSummary());
    }

    public function test_get_summary_with_zero_rate(): void
    {
        // Zero-rate is not exempt — summary should show the rate name and 0.00%
        $zeroResult = new TaxResultData(
            taxAmount: 0,
            rateId: 'zero',
            rateName: 'Zero Rate',
            ratePercentage: 0,
            zoneId: 'zone-123',
            zoneName: 'Zone',
        );

        $this->assertEquals('Zero Rate (0.00%)', $zeroResult->getSummary());
    }

    public function test_has_compound_taxes_with_single_rate(): void
    {
        $result = new TaxResultData(
            taxAmount: 600,
            rateId: 'rate-123',
            rateName: 'Standard',
            ratePercentage: 600,
            zoneId: 'zone-123',
            zoneName: 'Zone',
            breakdown: [
                ['name' => 'Standard', 'rate' => 600, 'amount' => 600, 'is_compound' => false],
            ],
        );

        $this->assertFalse($result->hasCompoundTaxes());
    }

    public function test_has_compound_taxes_with_multiple_rates(): void
    {
        $result = new TaxResultData(
            taxAmount: 1400,
            rateId: 'rate-123',
            rateName: 'Standard',
            ratePercentage: 600,
            zoneId: 'zone-123',
            zoneName: 'Zone',
            breakdown: [
                ['name' => 'Base Tax', 'rate' => 600, 'amount' => 600, 'is_compound' => false],
                ['name' => 'Additional Tax', 'rate' => 800, 'amount' => 800, 'is_compound' => true],
            ],
        );

        $this->assertTrue($result->hasCompoundTaxes());
    }

    public function test_has_compound_taxes_false_for_two_non_compound_rates(): void
    {
        // Two non-compound rates is NOT compound taxation.
        $result = new TaxResultData(
            taxAmount: 1100,
            rateId: 'rate-123',
            rateName: 'Standard',
            ratePercentage: 600,
            zoneId: 'zone-123',
            zoneName: 'Zone',
            breakdown: [
                ['name' => 'SST', 'rate' => 600, 'amount' => 600, 'is_compound' => false],
                ['name' => 'Service Tax', 'rate' => 500, 'amount' => 500, 'is_compound' => false],
            ],
        );

        $this->assertFalse($result->hasCompoundTaxes());
    }
}
