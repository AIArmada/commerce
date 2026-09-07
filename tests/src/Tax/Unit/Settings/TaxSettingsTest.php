<?php

declare(strict_types=1);

use AIArmada\Tax\Settings\TaxSettings;

/**
 * Create a TaxSettings instance with mocked properties.
 *
 * @param  array<string, mixed>  $properties
 */
$createPartialMockSettings = function (array $properties): TaxSettings {
    $reflection = new ReflectionClass(TaxSettings::class);
    $settings = $reflection->newInstanceWithoutConstructor();

    foreach ($properties as $property => $value) {
        $settings->{$property} = $value;
    }

    return $settings;
};

describe('TaxSettings', function (): void {
    it('settings group', function (): void {
        $this->assertEquals('tax', TaxSettings::group());
    });

    it('settings has required properties', function (): void {
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
    });
});
