<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource;

it('country resource is enabled by default', function (): void {
    expect(config('filament-addressing.resources.countries.enabled'))->toBeTrue();
});

it('country resource is read-only by default', function (): void {
    expect(AddressCountryResource::isReadOnly())->toBeTrue();
});

it('country resource has correct model', function (): void {
    expect(AddressCountryResource::getModel())->toBe(AddressCountry::class);
});

it('country resource does not expose delete bulk action by default', function (): void {
    expect(config('filament-addressing.resources.countries.read_only'))->toBeTrue();
});

it('country resource navigation icon is configured', function (): void {
    expect(config('filament-addressing.navigation.icons.countries'))->toBe('heroicon-o-globe-alt');
});
