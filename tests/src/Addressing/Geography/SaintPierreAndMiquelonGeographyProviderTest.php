<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintPierreAndMiquelon\SaintPierreAndMiquelonAddressFormatter;
use AIArmada\Addressing\Geography\SaintPierreAndMiquelon\SaintPierreAndMiquelonGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SaintPierreAndMiquelonGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('overseas_collectivity')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Saint Pierre and Miquelon tree with state links', function (): void {
    $this->seedCountry('PM');

    $result = app(SeedCountryGeographiesAction::class)->execute('PM');
    $country = AddressCountry::query()->where('iso2', 'PM')->firstOrFail();

    expect($result['seeded'])->toContain('PM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'overseas_collectivity')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(1);
});

it('formats Saint Pierre and Miquelon addresses with the code left of the locality', function (): void {
    $formatted = app(SaintPierreAndMiquelonAddressFormatter::class)->format(AddressData::from([
        'line1' => '24 rue de Paris',
        'city' => 'Saint-Pierre',
        'postcode' => '97500',
        'country_code' => 'PM',
    ]));

    expect($formatted)->toBe("24 rue de Paris\n97500 Saint-Pierre\nSaint Pierre and Miquelon");
});

it('uses the same code for Miquelon', function (): void {
    $formatted = app(SaintPierreAndMiquelonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Chapelle',
        'city' => 'Miquelon',
        'postcode' => '97500',
        'country_code' => 'PM',
    ]));

    expect($formatted)->toBe("Rue de la Chapelle\n97500 Miquelon\nSaint Pierre and Miquelon");
});
