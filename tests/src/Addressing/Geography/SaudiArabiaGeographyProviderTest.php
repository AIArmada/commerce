<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 13 Saudi states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SA')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '02',
        'name' => 'Makk',
        'label' => 'Makk',
    ]);

    app(SaudiArabiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Makkah')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(13);
});

it('maps every Saudi state code to its area', function (): void {
    $mappings = app(SaudiArabiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(13)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '14']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SaudiArabiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Saudi tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SA');
    $country = AddressCountry::query()->where('iso2', 'SA')->firstOrFail();

    expect($result['seeded'])->toContain('SA')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(13)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(13);
});
