<?php

declare(strict_types=1);

use AIArmada\Tax\Settings\TaxZoneSettings;
use ReflectionClass;
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

    it('settings can be instantiated without constructor', function (): void {
        $reflection = new ReflectionClass(TaxZoneSettings::class);
        $settings = $reflection->newInstanceWithoutConstructor();

        $this->assertInstanceOf(TaxZoneSettings::class, $settings);
    });

    it('settings properties are accessible', function (): void {
        $reflection = new ReflectionClass(TaxZoneSettings::class);
        $settings = $reflection->newInstanceWithoutConstructor();

        $settings->multiZoneEnabled = true;
        $settings->defaultZoneId = 'zone-123';
        $settings->autoDetectZone = true;
        $settings->fallbackBehavior = 'default';
        $settings->compoundTaxEnabled = false;
        $settings->showTaxBreakdown = true;

        $this->assertTrue($settings->multiZoneEnabled);
        $this->assertEquals('zone-123', $settings->defaultZoneId);
        $this->assertTrue($settings->autoDetectZone);
        $this->assertEquals('default', $settings->fallbackBehavior);
        $this->assertFalse($settings->compoundTaxEnabled);
        $this->assertTrue($settings->showTaxBreakdown);
    });

    it('settings default zone id can be null', function (): void {
        $reflection = new ReflectionClass(TaxZoneSettings::class);
        $settings = $reflection->newInstanceWithoutConstructor();

        $settings->defaultZoneId = null;

        $this->assertNull($settings->defaultZoneId);
    });
});
