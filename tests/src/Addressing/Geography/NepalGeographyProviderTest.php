<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Nepal\NepalAddressFormatter;
use AIArmada\Addressing\Geography\Nepal\NepalGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NepalGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Nepali tree with state links', function (): void {
    $this->seedCountry('NP');

    $result = app(SeedCountryGeographiesAction::class)->execute('NP');
    $country = AddressCountry::query()->where('iso2', 'NP')->firstOrFail();

    expect($result['seeded'])->toContain('NP')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(7)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);
});

it('formats Nepali addresses with the postcode right of the locality', function (): void {
    $formatted = app(NepalAddressFormatter::class)->format(AddressData::from([
        'line1' => '102, Mitery Marg',
        'line2' => 'Baneshwore',
        'city' => 'KATHMANDU',
        'state' => 'Bagmati',
        'postcode' => '44601',
        'country_code' => 'NP',
    ]));

    expect($formatted)->toBe("102, Mitery Marg\nBaneshwore\nKATHMANDU 44601\nBagmati\nNepal");
});
it('formats Nepali addresses without a postcode when missing', function (): void {
    $formatted = app(NepalAddressFormatter::class)->format(AddressData::from([
        'line1' => '102, Mitery Marg',
        'city' => 'KATHMANDU',
        'state' => 'Bagmati',
        'country_code' => 'NP',
    ]));

    expect($formatted)->toBe("102, Mitery Marg\nKATHMANDU\nBagmati\nNepal");
});
