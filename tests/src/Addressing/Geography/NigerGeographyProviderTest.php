<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Niger\NigerAddressFormatter;
use AIArmada\Addressing\Geography\Niger\NigerGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NigerGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Nigerien tree with state links', function (): void {
    $this->seedCountry('NE');

    $result = app(SeedCountryGeographiesAction::class)->execute('NE');
    $country = AddressCountry::query()->where('iso2', 'NE')->firstOrFail();

    expect($result['seeded'])->toContain('NE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'urban_community')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(8);
});

it('formats Nigerien addresses with the postcode left of the locality', function (): void {
    $formatted = app(NigerAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 502',
        'city' => 'NIAMEY',
        'postcode' => '8001',
        'country_code' => 'NE',
    ]));

    expect($formatted)->toBe("BP 502\n8001 NIAMEY\nNiger");
});
it('formats Nigerien addresses keeping the abbreviated capital above the region', function (): void {
    $formatted = app(NigerAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 502',
        'city' => 'NY',
        'state' => 'Niamey',
        'postcode' => '8000',
        'country_code' => 'NE',
    ]));

    expect($formatted)->toBe("BP 502\n8000 NY\nNiamey\nNiger");
});
