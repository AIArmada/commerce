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
