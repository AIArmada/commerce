<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\FilamentAddressing\Resources\AddressCityResource;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource;
use AIArmada\FilamentAddressing\Resources\AddressStateResource;

it('country resource is enabled by default', function (): void {
    expect(config('filament-addressing.resources.countries.enabled'))->toBeTrue();
});

it('country resource is read-only by default', function (): void {
    expect(AddressCountryResource::isReadOnly())->toBeTrue();
});

it('country resource navigation sort is configured', function (): void {
    expect(AddressCountryResource::getNavigationSort())->toBe(80);
});

it('country resource navigation icon follows config', function (): void {
    $original = config('filament-addressing.navigation.icons.countries');

    config()->set('filament-addressing.navigation.icons.countries', 'heroicon-o-flag');

    try {
        expect(AddressCountryResource::getNavigationIcon())->toBe('heroicon-o-flag');
    } finally {
        config()->set('filament-addressing.navigation.icons.countries', $original);
    }
});

it('country editing must be explicitly enabled before editing is allowed', function (): void {
    $originalReadOnly = config('filament-addressing.resources.countries.read_only', true);
    $originalCountryEditing = config('filament-addressing.features.country_editing', false);

    config()->set('filament-addressing.resources.countries.read_only', false);
    config()->set('filament-addressing.features.country_editing', false);

    try {
        expect(AddressCountryResource::isReadOnly())->toBeTrue();

        config()->set('filament-addressing.features.country_editing', true);

        expect(AddressCountryResource::isReadOnly())->toBeFalse();
    } finally {
        config()->set('filament-addressing.resources.countries.read_only', $originalReadOnly);
        config()->set('filament-addressing.features.country_editing', $originalCountryEditing);
    }
});

it('country resource has correct model', function (): void {
    expect(AddressCountryResource::getModel())->toBe(AddressCountry::class);
});

it('country resource does not expose delete bulk action by default', function (): void {
    expect(config('filament-addressing.resources.countries.read_only'))->toBeTrue();
});

it('state and city resources honor read-only configuration', function (): void {
    $stateReadOnly = config('filament-addressing.resources.states.read_only');
    $cityReadOnly = config('filament-addressing.resources.cities.read_only');

    config()->set('filament-addressing.resources.states.read_only', true);
    config()->set('filament-addressing.resources.cities.read_only', true);

    try {
        expect(AddressStateResource::getPages())->not->toHaveKey('edit')
            ->and(AddressCityResource::getPages())->not->toHaveKey('edit');
    } finally {
        config()->set('filament-addressing.resources.states.read_only', $stateReadOnly);
        config()->set('filament-addressing.resources.cities.read_only', $cityReadOnly);
    }
});
