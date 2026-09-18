<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Moldova\MoldovaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 37 Moldovan states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MD')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'AN',
        'name' => 'Anenii Noi (legacy)',
        'label' => 'Anenii Noi (legacy)',
    ]);

    app(MoldovaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Anenii Noi')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(37);
});

it('maps every Moldovan state code to its area', function (): void {
    $mappings = app(MoldovaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(37)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['AN', 'BA', 'BS', 'BD', 'BR', 'CA', 'CL', 'CT', 'CS', 'CU', 'CM', 'CR', 'DO', 'DR', 'DU', 'ED', 'FA', 'FL', 'GA', 'GL', 'HI', 'IA', 'LE', 'NI', 'OC', 'OR', 'RE', 'RI', 'SI', 'SD', 'SO', 'SV', 'ST', 'TA', 'TE', 'SN', 'UN']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MoldovaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Moldovan tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MD');
    $country = AddressCountry::query()->where('iso2', 'MD')->firstOrFail();

    expect($result['seeded'])->toContain('MD')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(37)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(32)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_territorial_unit')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'territorial_unit')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(37);
});
