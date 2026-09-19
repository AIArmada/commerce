<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Myanmar\MyanmarAddressFormatter;
use AIArmada\Addressing\Geography\Myanmar\MyanmarGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MyanmarGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Myanmar tree with state links', function (): void {
    $this->seedCountry('MM');

    $result = app(SeedCountryGeographiesAction::class)->execute('MM');
    $country = AddressCountry::query()->where('iso2', 'MM')->firstOrFail();

    expect($result['seeded'])->toContain('MM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'union_territory')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(15);
});

it('formats Myanmar addresses with the locality, postcode and region', function (): void {
    $formatted = app(MyanmarAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No. 7(A) 58 Street, Between 40 x 41',
        'city' => 'Pyigyitagon Township',
        'state' => 'Mandalay',
        'postcode' => '0505001',
        'country_code' => 'MM',
    ]));

    expect($formatted)->toBe("No. 7(A) 58 Street, Between 40 x 41\nPyigyitagon Township, 0505001\nMandalay\nMyanmar");
});
