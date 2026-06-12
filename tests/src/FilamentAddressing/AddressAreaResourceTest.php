<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;

it('area resource is enabled by default', function (): void {
    expect(config('filament-addressing.resources.areas.enabled'))->toBeTrue();
});

it('area resource is not read-only by default', function (): void {
    expect(AddressAreaResource::isReadOnly())->toBeFalse();
});

it('area resource has correct model', function (): void {
    expect(AddressAreaResource::getModel())->toBe(AddressArea::class);
});

it('area import is enabled by default', function (): void {
    expect(config('filament-addressing.features.area_import'))->toBeTrue();
});

it('area export is enabled by default', function (): void {
    expect(config('filament-addressing.features.area_export'))->toBeTrue();
});
