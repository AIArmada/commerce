<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\ElSalvador\ElSalvadorAddressFormatter;
use AIArmada\Addressing\Geography\ElSalvador\ElSalvadorGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ElSalvadorGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Salvadoran tree with state links', function (): void {
    $this->seedCountry('SV');

    $result = app(SeedCountryGeographiesAction::class)->execute('SV');
    $country = AddressCountry::query()->where('iso2', 'SV')->firstOrFail();

    expect($result['seeded'])->toContain('SV')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});

it('formats Salvadoran addresses with the postcode left of the locality', function (): void {
    $formatted = app(ElSalvadorAddressFormatter::class)->format(AddressData::from([
        'line1' => '6a AVENIDA NORTE 165',
        'city' => 'SAN SALVADOR',
        'postcode' => '2201',
        'country_code' => 'SV',
    ]));

    expect($formatted)->toBe("6a AVENIDA NORTE 165\n2201 SAN SALVADOR\nEl Salvador");
});
it('formats Salvadoran box addresses with the branch postcode', function (): void {
    $formatted = app(ElSalvadorAddressFormatter::class)->format(AddressData::from([
        'line1' => 'APARTADO POSTAL 131',
        'line2' => 'SUCURSAL SOPAYANGO',
        'city' => 'SAN SALVADOR',
        'postcode' => '1116',
        'country_code' => 'SV',
    ]));

    expect($formatted)->toBe("APARTADO POSTAL 131\nSUCURSAL SOPAYANGO\n1116 SAN SALVADOR\nEl Salvador");
});
