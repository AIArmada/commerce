<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Iceland\IcelandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 72 Icelandic states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'IS')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'AKN',
        'name' => 'Akranes (legacy)',
        'label' => 'Akranes (legacy)',
    ]);

    app(IcelandGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Akranes')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(72);
});

it('maps every Icelandic state code to its area', function (): void {
    $mappings = app(IcelandGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(72)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['AKN', 'AKU', 'SFA', 'ARN', 'ASA', 'BLA', 'BOL', 'BOG', '1', 'DAB', 'DAV', '7', 'EOM', 'EYF', 'FJL', 'FJD', 'FLR', 'FLA', 'GAR', 'GOG', 'GRN', 'GRU', 'GRY', 'HAF', 'HRG', 'SHF', 'HRU', 'HUG', 'HUV', 'HVA', 'HVE', 'ISA', 'KAL', 'KJO', 'KOP', 'LAN', 'MOS', 'MUL', 'MYR', 'NOR', '6', '5', 'SOL', 'RGE', 'RGY', 'RHH', 'RKN', 'RKV', 'SEL', 'SKF', 'SKG', 'SKR', 'SSS', 'SOG', 'SKO', 'SNF', '8', '2', 'STR', 'STY', 'SDV', 'SDN', 'SBT', 'TAL', 'TJO', 'VEM', 'VER', 'SVG', 'VOP', '3', '4', 'THG']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IcelandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Icelandic tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('IS');
    $country = AddressCountry::query()->where('iso2', 'IS')->firstOrFail();

    expect($result['seeded'])->toContain('IS')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(72)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(64)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(72);
});
