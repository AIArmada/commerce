<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Settings;

use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\Tax\Settings\TaxZoneSettings;
use ReflectionClass;

class TaxZoneSettingsTest extends TaxTestCase
{
    public function test_settings_group(): void
    {
        $this->assertEquals('tax_zones', TaxZoneSettings::group());
    }

    public function test_settings_has_required_properties(): void
    {
        $reflection = new ReflectionClass(TaxZoneSettings::class);

        $this->assertTrue($reflection->hasProperty('multiZoneEnabled'));
        $this->assertTrue($reflection->hasProperty('defaultZoneId'));
        $this->assertTrue($reflection->hasProperty('autoDetectZone'));
        $this->assertTrue($reflection->hasProperty('fallbackBehavior'));
        $this->assertTrue($reflection->hasProperty('compoundTaxEnabled'));
        $this->assertTrue($reflection->hasProperty('showTaxBreakdown'));
    }

    public function test_settings_properties_have_correct_types(): void
    {
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
    }

    public function test_settings_class_extends_spatie_settings(): void
    {
        $this->assertTrue(is_subclass_of(TaxZoneSettings::class, \Spatie\LaravelSettings\Settings::class));
    }

    public function test_settings_can_be_instantiated_without_constructor(): void
    {
        $reflection = new ReflectionClass(TaxZoneSettings::class);
        $settings = $reflection->newInstanceWithoutConstructor();

        $this->assertInstanceOf(TaxZoneSettings::class, $settings);
    }

    public function test_settings_properties_are_accessible(): void
    {
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
    }

    public function test_settings_default_zone_id_can_be_null(): void
    {
        $reflection = new ReflectionClass(TaxZoneSettings::class);
        $settings = $reflection->newInstanceWithoutConstructor();

        $settings->defaultZoneId = null;

        $this->assertNull($settings->defaultZoneId);
    }
}
