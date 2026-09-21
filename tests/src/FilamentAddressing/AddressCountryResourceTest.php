<?php

declare(strict_types=1);

use AIArmada\FilamentAddressing\Resources\AddressCityResource;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource;
use AIArmada\FilamentAddressing\Resources\AddressStateResource;

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
