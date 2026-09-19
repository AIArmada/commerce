<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Namibia\NamibiaAddressFormatter;
use AIArmada\Addressing\Geography\Namibia\NamibiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NamibiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Namibian tree with state links', function (): void {
    $this->seedCountry('NA');

    $result = app(SeedCountryGeographiesAction::class)->execute('NA');
    $country = AddressCountry::query()->where('iso2', 'NA')->firstOrFail();

    expect($result['seeded'])->toContain('NA')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});

it('formats Namibian addresses with the postcode below the locality', function (): void {
    $formatted = app(NamibiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Private bag 13678',
        'city' => 'WINDHOEK',
        'postcode' => '10005',
        'country_code' => 'NA',
    ]));

    expect($formatted)->toBe("Private bag 13678\nWINDHOEK\n10005\nNamibia");
});
it('formats Namibian post box addresses with the postcode below the office', function (): void {
    $formatted = app(NamibiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 999',
        'city' => 'OKAHANDJA',
        'postcode' => '12004',
        'country_code' => 'NA',
    ]));

    expect($formatted)->toBe("PO Box 999\nOKAHANDJA\n12004\nNamibia");
});
