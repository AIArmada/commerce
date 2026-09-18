<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Brazil\BrazilGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 27 Brazilian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BR')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'SP',
        'name' => 'Sao Paulo',
        'label' => 'Sao Paulo',
    ]);

    app(BrazilGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('São Paulo')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(27);
});

it('maps every Brazilian state code to its area', function (): void {
    $mappings = app(BrazilGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(27)
        ->and(array_keys($mappings))->toBe(['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BrazilGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Brazilian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('BR');
    $country = AddressCountry::query()->where('iso2', 'BR')->firstOrFail();

    expect($result['seeded'])->toContain('BR')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(27)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(26)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'federal_district')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(27);
});
