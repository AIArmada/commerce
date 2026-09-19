<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Portugal\PortugalAddressFormatter;
use AIArmada\Addressing\Geography\Portugal\PortugalGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PortugalGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Portuguese tree with state links', function (): void {
    $this->seedCountry('PT');

    $result = app(SeedCountryGeographiesAction::class)->execute('PT');
    $country = AddressCountry::query()->where('iso2', 'PT')->firstOrFail();

    expect($result['seeded'])->toContain('PT')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_region')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(20);
});

it('formats Portuguese addresses with the hyphenated postcode left of the locality', function (): void {
    $formatted = app(PortugalAddressFormatter::class)->format(AddressData::from([
        'line1' => 'R. LEAL DA CÂMARA 31 RC ESQ',
        'line2' => 'ALGUEIRÃO',
        'city' => 'MEM MARTINS',
        'postcode' => '2725-079',
        'country_code' => 'PT',
    ]));

    expect($formatted)->toBe("R. LEAL DA CÂMARA 31 RC ESQ\nALGUEIRÃO\n2725-079 MEM MARTINS\nPortugal");
});
it('formats Portuguese Lisbon addresses with the parish postcode', function (): void {
    $formatted = app(PortugalAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenida da Liberdade 100',
        'city' => 'LISBOA',
        'postcode' => '1601-801',
        'country_code' => 'PT',
    ]));

    expect($formatted)->toBe("Avenida da Liberdade 100\n1601-801 LISBOA\nPortugal");
});
