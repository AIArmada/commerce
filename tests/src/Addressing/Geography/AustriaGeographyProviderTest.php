<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Austria\AustriaAddressFormatter;
use AIArmada\Addressing\Geography\Austria\AustriaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AustriaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Austrian tree with state links', function (): void {
    $this->seedCountry('AT');

    $result = app(SeedCountryGeographiesAction::class)->execute('AT');
    $country = AddressCountry::query()->where('iso2', 'AT')->firstOrFail();

    expect($result['seeded'])->toContain('AT')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(9)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});

it('formats Austrian addresses with the postcode left of the locality', function (): void {
    $formatted = app(AustriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rennbahnweg 25/2/15',
        'city' => 'WIEN',
        'postcode' => '1220',
        'country_code' => 'AT',
    ]));

    expect($formatted)->toBe("Rennbahnweg 25/2/15\n1220 WIEN\nAustria");
});
it('formats Austrian rural addresses with the state below the postcode line', function (): void {
    $formatted = app(AustriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Dorfstrasse 7',
        'city' => 'Hallstatt',
        'state' => 'Upper Austria',
        'postcode' => '4830',
        'country_code' => 'AT',
    ]));

    expect($formatted)->toBe("Dorfstrasse 7\n4830 Hallstatt\nUpper Austria\nAustria");
});
