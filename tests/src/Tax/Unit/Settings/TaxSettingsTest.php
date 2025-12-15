<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Settings;

use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\Tax\Settings\TaxSettings;
use ReflectionClass;

class TaxSettingsTest extends TaxTestCase
{
    public function test_settings_group(): void
    {
        $this->assertEquals('tax', TaxSettings::group());
    }

    public function test_calculate_tax_when_enabled(): void
    {
        $settings = $this->createPartialMockSettings(['enabled' => true, 'defaultTaxRate' => 10.0]);

        $tax = $settings->calculateTax(10000);

        $this->assertEquals(1000, $tax);
    }

    public function test_calculate_tax_when_disabled(): void
    {
        $settings = $this->createPartialMockSettings(['enabled' => false, 'defaultTaxRate' => 10.0]);

        $tax = $settings->calculateTax(10000);

        $this->assertEquals(0, $tax);
    }

    public function test_extract_tax_when_enabled(): void
    {
        $settings = $this->createPartialMockSettings(['enabled' => true, 'defaultTaxRate' => 10.0]);

        $tax = $settings->extractTax(11000);

        $this->assertEquals(1000, $tax);
    }

    public function test_extract_tax_when_disabled(): void
    {
        $settings = $this->createPartialMockSettings(['enabled' => false, 'defaultTaxRate' => 10.0]);

        $tax = $settings->extractTax(11000);

        $this->assertEquals(0, $tax);
    }

    public function test_extract_tax_with_different_rate(): void
    {
        $settings = $this->createPartialMockSettings(['enabled' => true, 'defaultTaxRate' => 8.75]);

        $tax = $settings->extractTax(11000);

        $this->assertEquals(885, $tax);
    }

    public function test_calculate_tax_with_different_rates(): void
    {
        $settings10 = $this->createPartialMockSettings(['enabled' => true, 'defaultTaxRate' => 10.0]);
        $settings20 = $this->createPartialMockSettings(['enabled' => true, 'defaultTaxRate' => 20.0]);

        $this->assertEquals(1000, $settings10->calculateTax(10000));
        $this->assertEquals(2000, $settings20->calculateTax(10000));
    }

    public function test_calculate_tax_with_zero_amount(): void
    {
        $settings = $this->createPartialMockSettings(['enabled' => true, 'defaultTaxRate' => 10.0]);

        $tax = $settings->calculateTax(0);

        $this->assertEquals(0, $tax);
    }

    public function test_extract_tax_with_zero_amount(): void
    {
        $settings = $this->createPartialMockSettings(['enabled' => true, 'defaultTaxRate' => 10.0]);

        $tax = $settings->extractTax(0);

        $this->assertEquals(0, $tax);
    }

    public function test_settings_has_required_properties(): void
    {
        $reflection = new ReflectionClass(TaxSettings::class);

        $this->assertTrue($reflection->hasProperty('enabled'));
        $this->assertTrue($reflection->hasProperty('defaultTaxRate'));
        $this->assertTrue($reflection->hasProperty('defaultTaxName'));
        $this->assertTrue($reflection->hasProperty('pricesIncludeTax'));
        $this->assertTrue($reflection->hasProperty('taxBasedOnShippingAddress'));
        $this->assertTrue($reflection->hasProperty('digitalGoodsTaxable'));
        $this->assertTrue($reflection->hasProperty('shippingTaxable'));
        $this->assertTrue($reflection->hasProperty('taxIdLabel'));
        $this->assertTrue($reflection->hasProperty('validateTaxIds'));
        $this->assertTrue($reflection->hasProperty('requireExemptionCertificate'));
    }

    /**
     * Create a TaxSettings instance with mocked properties.
     *
     * @param  array<string, mixed>  $properties
     */
    protected function createPartialMockSettings(array $properties): TaxSettings
    {
        $reflection = new ReflectionClass(TaxSettings::class);
        $settings = $reflection->newInstanceWithoutConstructor();

        foreach ($properties as $property => $value) {
            $settings->{$property} = $value;
        }

        return $settings;
    }
}
