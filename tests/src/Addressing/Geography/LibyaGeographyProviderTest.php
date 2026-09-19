<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Libya\LibyaAddressFormatter;
use AIArmada\Addressing\Geography\Libya\LibyaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LibyaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('popularate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Libyan tree with state links', function (): void {
    $this->seedCountry('LY');

    $result = app(SeedCountryGeographiesAction::class)->execute('LY');
    $country = AddressCountry::query()->where('iso2', 'LY')->firstOrFail();

    expect($result['seeded'])->toContain('LY')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'popularate')->count())->toBe(22)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(22);
});

it('formats Libyan addresses without a postcode system', function (): void {
    $formatted = app(LibyaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. Al Ghazaly 12',
        'city' => 'TRIPOLI',
        'country_code' => 'LY',
    ]));

    expect($formatted)->toBe("Av. Al Ghazaly 12\nTRIPOLI\nLibya");
});
it('prints any supplied Libyan code on its own line', function (): void {
    $formatted = app(LibyaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. Al Ghazaly 12',
        'city' => 'TRIPOLI',
        'postcode' => '99999',
        'country_code' => 'LY',
    ]));

    expect($formatted)->toBe("Av. Al Ghazaly 12\nTRIPOLI\n99999\nLibya");
});
