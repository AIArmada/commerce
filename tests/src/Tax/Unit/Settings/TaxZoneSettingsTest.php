<?php

declare(strict_types=1);

use AIArmada\Tax\Settings\TaxZoneSettings;
use Spatie\LaravelSettings\Settings;

describe('TaxZoneSettings', function (): void {
    it('settings group', function (): void {
        $this->assertEquals('tax_zones', TaxZoneSettings::group());
    });

    it('settings has required properties', function (): void {
        $reflection = new ReflectionClass(TaxZoneSettings::class);

        $this->assertTrue($reflection->hasProperty('multiZoneEnabled'));
        $this->assertTrue($reflection->hasProperty('defaultZoneId'));
        $this->assertTrue($reflection->hasProperty('autoDetectZone'));
        $this->assertTrue($reflection->hasProperty('fallbackBehavior'));
        $this->assertTrue($reflection->hasProperty('compoundTaxEnabled'));
        $this->assertTrue($reflection->hasProperty('showTaxBreakdown'));
    });

    it('settings properties have correct types', function (): void {
        $reflection = new ReflectionClass(TaxZoneSettings::class);

        $multiZoneEnabled = $reflection->getProperty('multiZoneEnabled');
        $this->assertEquals('bool', $multiZoneEnabled->getType()?->getName());

        $defaultZoneId = $reflection->getProperty('defaultZoneId');
        $this->assertTrue($defaultZoneId->getType()?->allowsNull());

        $autoDetectZone = $reflection->getProperty('autoDetectZone');
        $this->assertEquals('bool', $autoDetectZone->getType()?->getName());

        $fallbackBehavior = $reflection->getProperty('fallbackBehavior');
        $this->assertEquals('string', $fallbackBehavior->getType()?->getName());

        $compoundTaxEnabled = $reflection->getProperty('compoundTaxEnabled');
        $this->assertEquals('bool', $compoundTaxEnabled->getType()?->getName());

        $showTaxBreakdown = $reflection->getProperty('showTaxBreakdown');
        $this->assertEquals('bool', $showTaxBreakdown->getType()?->getName());
    });

    it('settings class extends spatie settings', function (): void {
        $this->assertTrue(is_subclass_of(TaxZoneSettings::class, Settings::class));
    });
});
