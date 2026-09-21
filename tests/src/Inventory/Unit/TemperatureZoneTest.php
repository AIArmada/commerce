<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\TemperatureZone;

test('TemperatureZone isCompatibleWith works correctly', function (): void {
    expect(TemperatureZone::Ambient->isCompatibleWith(TemperatureZone::Ambient))->toBeTrue();
    expect(TemperatureZone::Ambient->isCompatibleWith(TemperatureZone::Controlled))->toBeTrue();
    expect(TemperatureZone::Chilled->isCompatibleWith(TemperatureZone::Frozen))->toBeFalse();
    expect(TemperatureZone::Frozen->isCompatibleWith(TemperatureZone::DeepFrozen))->toBeTrue();
    expect(TemperatureZone::DeepFrozen->isCompatibleWith(TemperatureZone::Frozen))->toBeTrue();
    expect(TemperatureZone::Controlled->isCompatibleWith(TemperatureZone::Ambient))->toBeTrue();
    expect(TemperatureZone::Controlled->isCompatibleWith(TemperatureZone::ClimateControlled))->toBeTrue();
});
