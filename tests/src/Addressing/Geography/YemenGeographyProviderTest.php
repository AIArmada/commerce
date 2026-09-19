<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Yemen\YemenAddressFormatter;
use AIArmada\Addressing\Geography\Yemen\YemenGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(YemenGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Yemeni tree with state links', function (): void {
    $this->seedCountry('YE');

    $result = app(SeedCountryGeographiesAction::class)->execute('YE');
    $country = AddressCountry::query()->where('iso2', 'YE')->firstOrFail();

    expect($result['seeded'])->toContain('YE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(21)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(22);
});

it('formats Yemeni addresses without a postcode system', function (): void {
    $formatted = app(YemenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 1993',
        'city' => "SANA'A",
        'country_code' => 'YE',
    ]));

    expect($formatted)->toBe("B.P. 1993\nSANA'A\nYemen");
});
it('prints matching Yemeni city and governorate once', function (): void {
    $formatted = app(YemenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al Wahda Street 2',
        'city' => 'Ibb',
        'state' => 'Ibb',
        'country_code' => 'YE',
    ]));

    expect($formatted)->toBe("Al Wahda Street 2\nIbb\nYemen");
});
