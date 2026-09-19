<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweAddressFormatter;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ZimbabweGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Zimbabwean tree with state links', function (): void {
    $this->seedCountry('ZW');

    $result = app(SeedCountryGeographiesAction::class)->execute('ZW');
    $country = AddressCountry::query()->where('iso2', 'ZW')->firstOrFail();

    expect($result['seeded'])->toContain('ZW')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(10);
});

it('formats Zimbabwean addresses without a postcode system', function (): void {
    $formatted = app(ZimbabweAddressFormatter::class)->format(AddressData::from([
        'line1' => '34–6th Crescent',
        'line2' => 'Warren Park 1',
        'city' => 'HARARE',
        'country_code' => 'ZW',
    ]));

    expect($formatted)->toBe("34–6th Crescent\nWarren Park 1\nHARARE\nZimbabwe");
});
it('prints matching Zimbabwean city and province once', function (): void {
    $formatted = app(ZimbabweAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 Josiah Tongogara Street',
        'city' => 'Bulawayo',
        'state' => 'Bulawayo',
        'country_code' => 'ZW',
    ]));

    expect($formatted)->toBe("12 Josiah Tongogara Street\nBulawayo\nZimbabwe");
});
