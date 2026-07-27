<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SearchAddressAreasAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Models\AddressArea;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    app(SeedCountryGeographiesAction::class)->execute('MY');
});

it('searches Malaysia localities by name and role', function (): void {
    $results = app(SearchAddressAreasAction::class)->execute(
        query: 'Wangsa',
        countryCode: 'MY',
        role: 'postal_locality',
    );

    expect($results->pluck('name')->all())->toContain('Wangsa Maju')
        ->and($results->first()->roles()->where('role', 'postal_locality')->exists())->toBeTrue();
});

it('searches federal territory aliases', function (): void {
    $results = app(SearchAddressAreasAction::class)->execute(query: 'KL', countryCode: 'MY');

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Wilayah Persekutuan Kuala Lumpur');
});

it('creates postal hierarchy relationships during import', function (): void {
    $locality = AddressArea::query()
        ->where('country_code', 'MY')
        ->where('name', 'Wangsa Maju')
        ->firstOrFail();

    expect($locality->parent_id)->not->toBeNull()
        ->and($locality->parent->name)->toBe('Wilayah Persekutuan Kuala Lumpur')
        ->and($locality->parent->relatedAreas()->whereKey($locality->getKey())->wherePivot('hierarchy_type', 'postal')->exists())->toBeTrue();
});

it('keeps administrative relationships separate from postal relationships', function (): void {
    $district = AddressArea::query()
        ->where('country_code', 'MY')
        ->where('type', 'district')
        ->firstOrFail();

    $results = app(SearchAddressAreasAction::class)->execute(
        query: $district->name,
        countryCode: 'MY',
        hierarchyType: 'administrative',
    );

    expect($district->ancestors()->wherePivot('hierarchy_type', 'administrative')->exists())->toBeTrue()
        ->and($results->pluck('id')->all())->toContain($district->getKey());
});

it('does not cross hierarchy branches when filtering by parent', function (): void {
    $state = AddressArea::query()
        ->where('country_code', 'MY')
        ->where('type', 'state')
        ->where('name', 'Johor')
        ->firstOrFail();

    $results = app(SearchAddressAreasAction::class)->execute(
        query: 'Johor Bahru',
        countryCode: 'MY',
        parentId: $state->getKey(),
        hierarchyType: 'postal',
    );

    expect($results->pluck('name')->all())->not->toContain('Johor Bahru');
});
