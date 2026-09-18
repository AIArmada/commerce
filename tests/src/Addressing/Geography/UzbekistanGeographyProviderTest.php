<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 14 Uzbek states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'UZ')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'TK',
        'name' => 'Tashkent',
        'label' => 'Tashkent',
    ]);

    app(UzbekistanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Tashkent City')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14);
});

it('maps every Uzbek state code to its area', function (): void {
    $mappings = app(UzbekistanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(14)
        ->and(array_keys($mappings))->toBe(['AN', 'BU', 'FA', 'JI', 'NG', 'NW', 'QA', 'QR', 'SA', 'SI', 'SU', 'TK', 'TO', 'XO']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(UzbekistanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Uzbek tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('UZ');
    $country = AddressCountry::query()->where('iso2', 'UZ')->firstOrFail();

    expect($result['seeded'])->toContain('UZ')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'republic')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});
