<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Micronesia\MicronesiaAddressFormatter;
use AIArmada\Addressing\Geography\Micronesia\MicronesiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MicronesiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Micronesian tree with state links', function (): void {
    $this->seedCountry('FM');

    $result = app(SeedCountryGeographiesAction::class)->execute('FM');
    $country = AddressCountry::query()->where('iso2', 'FM')->firstOrFail();

    expect($result['seeded'])->toContain('FM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(4)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(4);
});

it('formats Micronesian addresses with the US ZIP layout', function (): void {
    $formatted = app(MicronesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 123',
        'city' => 'Pohnpei',
        'postcode' => '96941',
        'country_code' => 'FM',
    ]));

    expect($formatted)->toBe("PO Box 123\nPohnpei FM 96941\nMicronesia");
});
it('formats Chuuk addresses with its own ZIP', function (): void {
    $formatted = app(MicronesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 9',
        'city' => 'Weno',
        'postcode' => '96942',
        'country_code' => 'FM',
    ]));

    expect($formatted)->toBe("PO Box 9\nWeno FM 96942\nMicronesia");
});
