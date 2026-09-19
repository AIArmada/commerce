<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Aland\AlandAddressFormatter;
use AIArmada\Addressing\Geography\Aland\AlandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AlandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Alander tree with state links', function (): void {
    $this->seedCountry('AX');

    $result = app(SeedCountryGeographiesAction::class)->execute('AX');
    $country = AddressCountry::query()->where('iso2', 'AX')->firstOrFail();

    expect($result['seeded'])->toContain('AX')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(16)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(16);
});

it('formats Alander addresses with the prefixed postcode left of the town', function (): void {
    $formatted = app(AlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Stadshusparken',
        'city' => 'MARIEHAMN',
        'postcode' => 'AX-22100',
        'country_code' => 'AX',
    ]));

    expect($formatted)->toBe("Stadshusparken\nAX-22100 MARIEHAMN\nAland Islands");
});
it('formats Alander domestic addresses with a bare postcode', function (): void {
    $formatted = app(AlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Stadshusparken',
        'city' => 'MARIEHAMN',
        'postcode' => '22100',
        'country_code' => 'AX',
    ]));

    expect($formatted)->toBe("Stadshusparken\n22100 MARIEHAMN\nAland Islands");
});
