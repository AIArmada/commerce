<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Colombia\ColombiaAddressFormatter;
use AIArmada\Addressing\Geography\Colombia\ColombiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ColombiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Colombian tree with state links', function (): void {
    $this->seedCountry('CO');

    $result = app(SeedCountryGeographiesAction::class)->execute('CO');
    $country = AddressCountry::query()->where('iso2', 'CO')->firstOrFail();

    expect($result['seeded'])->toContain('CO')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(33)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(33)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(32)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_district')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(33);
});

it('formats Colombian addresses with the postcode right and department below', function (): void {
    $formatted = app(ColombiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'CARRERA 7 NO. 27-18',
        'city' => 'PLANETA RICA',
        'state' => 'CORDOBA',
        'postcode' => '233057',
        'country_code' => 'CO',
    ]));

    expect($formatted)->toBe("CARRERA 7 NO. 27-18\nPLANETA RICA 233057\nCORDOBA\nColombia");
});
