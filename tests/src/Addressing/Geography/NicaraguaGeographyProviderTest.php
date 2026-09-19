<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Nicaragua\NicaraguaAddressFormatter;
use AIArmada\Addressing\Geography\Nicaragua\NicaraguaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NicaraguaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Nicaraguan tree with state links', function (): void {
    $this->seedCountry('NI');

    $result = app(SeedCountryGeographiesAction::class)->execute('NI');
    $country = AddressCountry::query()->where('iso2', 'NI')->firstOrFail();

    expect($result['seeded'])->toContain('NI')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_region')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(17);
});

it('formats Nicaraguan addresses with the postcode above the municipality', function (): void {
    $formatted = app(NicaraguaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Portón Cementerio General 1c Este, 1/2c Norte. Barrio Santa Ana Sur.',
        'city' => 'Managua',
        'state' => 'Managua',
        'postcode' => '12005',
        'country_code' => 'NI',
    ]));

    expect($formatted)->toBe("Portón Cementerio General 1c Este, 1/2c Norte. Barrio Santa Ana Sur.\n12005\nManagua\nNicaragua");
});
it('formats Nicaraguan Granada addresses with the town postcode', function (): void {
    $formatted = app(NicaraguaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle La Calzada 4',
        'city' => 'Granada',
        'postcode' => '43000',
        'country_code' => 'NI',
    ]));

    expect($formatted)->toBe("Calle La Calzada 4\n43000\nGranada\nNicaragua");
});
