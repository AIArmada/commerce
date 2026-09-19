<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Hungary\HungaryAddressFormatter;
use AIArmada\Addressing\Geography\Hungary\HungaryGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(HungaryGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Hungarian tree with state links', function (): void {
    $this->seedCountry('HU');

    $result = app(SeedCountryGeographiesAction::class)->execute('HU');
    $country = AddressCountry::query()->where('iso2', 'HU')->firstOrFail();

    expect($result['seeded'])->toContain('HU')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(43)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(43)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city_with_county_rights')->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(43);
});

it('formats Hungarian addresses with the postcode and town on one line', function (): void {
    $formatted = app(HungaryAddressFormatter::class)->format(AddressData::from([
        'line1' => 'VIRÁG TÉR 3. IV. 61',
        'city' => 'BUDAPEST',
        'postcode' => '1037',
        'country_code' => 'HU',
    ]));

    expect($formatted)->toBe("VIRÁG TÉR 3. IV. 61\n1037 BUDAPEST\nHungary");
});
it('formats Hungarian post box addresses with the box postcode', function (): void {
    $formatted = app(HungaryAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PF. 83',
        'city' => 'DABAS',
        'postcode' => '2380',
        'country_code' => 'HU',
    ]));

    expect($formatted)->toBe("PF. 83\n2380 DABAS\nHungary");
});
