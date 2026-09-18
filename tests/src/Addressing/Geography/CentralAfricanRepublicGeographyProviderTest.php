<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 17 Central African states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'CF')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BB',
        'name' => 'Bamingui-Bangoran (legacy)',
        'label' => 'Bamingui-Bangoran (legacy)',
    ]);

    app(CentralAfricanRepublicGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Bamingui-Bangoran')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(17);
});

it('maps every Central African state code to its area', function (): void {
    $mappings = app(CentralAfricanRepublicGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(17)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BB', 'BGF', 'BK', 'HM', 'HK', 'KG', 'LB', 'HS', 'MB', 'KB', 'NM', 'MP', 'UK', 'AC', 'OP', 'SE', 'VK']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CentralAfricanRepublicGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('prefecture')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Central African tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('CF');
    $country = AddressCountry::query()->where('iso2', 'CF')->firstOrFail();

    expect($result['seeded'])->toContain('CF')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'prefecture')->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'commune')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'economic_prefecture')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(17);
});
