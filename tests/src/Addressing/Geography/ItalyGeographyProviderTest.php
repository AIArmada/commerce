<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Italy\ItalyGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 20 Italian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'IT')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '52',
        'name' => 'Tuscany',
        'label' => 'Tuscany',
    ]);

    app(ItalyGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Toscana')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(20);
});

it('maps every Italian state code to its area', function (): void {
    $mappings = app(ItalyGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(20)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['21', '23', '25', '32', '34', '36', '42', '45', '52', '55', '57', '62', '65', '67', '72', '75', '77', '78', '82', '88']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ItalyGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Italian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('IT');
    $country = AddressCountry::query()->where('iso2', 'IT')->firstOrFail();

    expect($result['seeded'])->toContain('IT')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(20)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(20);

    $area = AddressArea::query()->where('source_id', 'it:region:toscana')->firstOrFail();

    expect(AddressAreaName::query()
        ->where('address_area_id', $area->getKey())
        ->where('name', 'Tuscany')
        ->exists())->toBeTrue();
});
