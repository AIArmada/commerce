<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Palau\PalauAddressFormatter;
use AIArmada\Addressing\Geography\Palau\PalauGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PalauGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Palauan tree with state links', function (): void {
    $this->seedCountry('PW');

    $result = app(SeedCountryGeographiesAction::class)->execute('PW');
    $country = AddressCountry::query()->where('iso2', 'PW')->firstOrFail();

    expect($result['seeded'])->toContain('PW')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(16)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(16);
});

it('formats Palauan addresses with the US ZIP layout', function (): void {
    $formatted = app(PalauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 100',
        'city' => 'Koror',
        'postcode' => '96940',
        'country_code' => 'PW',
    ]));

    expect($formatted)->toBe("PO Box 100\nKoror PW 96940\nPalau");
});

it('formats Palau ZIP+4 codes', function (): void {
    $formatted = app(PalauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 7',
        'city' => 'Koror',
        'postcode' => '96940-0100',
        'country_code' => 'PW',
    ]));

    expect($formatted)->toBe("PO Box 7\nKoror PW 96940-0100\nPalau");
});
