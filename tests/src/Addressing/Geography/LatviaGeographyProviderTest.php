<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Latvia\LatviaAddressFormatter;
use AIArmada\Addressing\Geography\Latvia\LatviaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LatviaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Latvian tree with state links', function (): void {
    $this->seedCountry('LV');

    $result = app(SeedCountryGeographiesAction::class)->execute('LV');
    $country = AddressCountry::query()->where('iso2', 'LV')->firstOrFail();

    expect($result['seeded'])->toContain('LV')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(43)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(43)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(36)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state_city')->count())->toBe(7)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(43);
});

it('formats Latvian addresses with the postcode right of the locality', function (): void {
    $formatted = app(LatviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Kr. Barona street 7, dz. 1',
        'city' => 'RIGA',
        'postcode' => 'LV-1050',
        'country_code' => 'LV',
    ]));

    expect($formatted)->toBe("Kr. Barona street 7, dz. 1\nRIGA, LV-1050\nLatvia");
});
it('formats Latvian sub-locality addresses with the office postcode', function (): void {
    $formatted = app(LatviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Valdemāra street 42, Ainaži',
        'city' => 'SALACGRIVAS NOV.',
        'postcode' => 'LV-4035',
        'country_code' => 'LV',
    ]));

    expect($formatted)->toBe("Valdemāra street 42, Ainaži\nSALACGRIVAS NOV., LV-4035\nLatvia");
});
