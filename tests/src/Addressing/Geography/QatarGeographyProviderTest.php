<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Qatar\QatarGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 8 Qatari states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'QA')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'SH',
        'name' => 'Shahaniya',
        'label' => 'Shahaniya',
    ]);

    app(QatarGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Al Sheehaniya')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(8);
});

it('maps every Qatari state code to its area', function (): void {
    $mappings = app(QatarGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(8)
        ->and(array_keys($mappings))->toBe(['DA', 'KH', 'MS', 'RA', 'SH', 'US', 'WA', 'ZA']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(QatarGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Qatari tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('QA');
    $country = AddressCountry::query()->where('iso2', 'QA')->firstOrFail();

    expect($result['seeded'])->toContain('QA')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(8)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(8);

    $area = AddressArea::query()->where('source_id', 'qa:municipality:doha')->firstOrFail();

    expect(AddressAreaName::query()
        ->where('address_area_id', $area->getKey())
        ->where('name', 'Ad Dawhah')
        ->exists())->toBeTrue();
});
