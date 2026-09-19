<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteAddressFormatter;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TimorLesteGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Timorese tree with state links', function (): void {
    $this->seedCountry('TL');

    $result = app(SeedCountryGeographiesAction::class)->execute('TL');
    $country = AddressCountry::query()->where('iso2', 'TL')->firstOrFail();

    expect($result['seeded'])->toContain('TL')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'AT')->where('name', 'Atauro')->exists())->toBeTrue()
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_administrative_region')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});

it('formats Timorese addresses with the postcode right of the municipality', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'AVENIDA CAPITA SINMAU',
        'state' => 'AINARO',
        'postcode' => 'TL42000',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("AVENIDA CAPITA SINMAU\nAINARO TL42000\nTimor-Leste");
});
it('formats Timorese addresses joining distinct city and municipality', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'TRAVESSA LAVANDARIA NO.12',
        'city' => 'Bairo Pite',
        'state' => 'DILI',
        'postcode' => 'TL11212',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("TRAVESSA LAVANDARIA NO.12\nBairo Pite - DILI TL11212\nTimor-Leste");
});
it('prints Timorese city-municipalities once when city and state match', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenida Presidente Nicolau Lobato',
        'city' => 'DILI',
        'state' => 'DILI',
        'postcode' => 'TL10901',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("Avenida Presidente Nicolau Lobato\nDILI TL10901\nTimor-Leste");
});
