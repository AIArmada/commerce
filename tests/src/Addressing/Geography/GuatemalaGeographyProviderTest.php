<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guatemala\GuatemalaAddressFormatter;
use AIArmada\Addressing\Geography\Guatemala\GuatemalaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GuatemalaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Guatemalan tree with state links', function (): void {
    $this->seedCountry('GT');

    $result = app(SeedCountryGeographiesAction::class)->execute('GT');
    $country = AddressCountry::query()->where('iso2', 'GT')->firstOrFail();

    expect($result['seeded'])->toContain('GT')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(22)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(22);
});

it('formats Guatemalan addresses with the postcode and hyphen left of the locality', function (): void {
    $formatted = app(GuatemalaAddressFormatter::class)->format(AddressData::from([
        'line1' => '6a. Avenida "A" 10-13, zona 1',
        'line2' => 'Centro Vivo, torre 2, Apartamento 608',
        'city' => 'Guatemala',
        'postcode' => '01001',
        'country_code' => 'GT',
    ]));

    expect($formatted)->toBe("6a. Avenida \"A\" 10-13, zona 1\nCentro Vivo, torre 2, Apartamento 608\n01001 - Guatemala\nGuatemala");
});
it('formats Guatemalan Villa Canales addresses with the town postcode', function (): void {
    $formatted = app(GuatemalaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle Principal 1',
        'city' => 'Villa Canales',
        'postcode' => '01065',
        'country_code' => 'GT',
    ]));

    expect($formatted)->toBe("Calle Principal 1\n01065 - Villa Canales\nGuatemala");
});
