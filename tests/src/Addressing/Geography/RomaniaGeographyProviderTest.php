<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Romania\RomaniaAddressFormatter;
use AIArmada\Addressing\Geography\Romania\RomaniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(RomaniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Romanian tree with state links', function (): void {
    $this->seedCountry('RO');

    $result = app(SeedCountryGeographiesAction::class)->execute('RO');
    $country = AddressCountry::query()->where('iso2', 'RO')->firstOrFail();

    expect($result['seeded'])->toContain('RO')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(42)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(42)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(41)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(42);
});

it('formats Romanian addresses with the postcode left of the locality', function (): void {
    $formatted = app(RomaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Drumul Taberei nr. 35, bl. F5, sc. 2, parter, ap. 23',
        'line2' => 'Sector 6',
        'city' => 'BUCHAREST',
        'postcode' => '061357',
        'country_code' => 'RO',
    ]));

    expect($formatted)->toBe("Drumul Taberei nr. 35, bl. F5, sc. 2, parter, ap. 23\nSector 6\n061357 BUCHAREST\nRomania");
});
it('formats Romanian addresses with the county below the postcode line', function (): void {
    $formatted = app(RomaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Strada Republicii 10',
        'city' => 'Craiova',
        'state' => 'Dolj',
        'postcode' => '200716',
        'country_code' => 'RO',
    ]));

    expect($formatted)->toBe("Strada Republicii 10\n200716 Craiova\nDolj\nRomania");
});
