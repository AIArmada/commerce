<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Greece\GreeceAddressFormatter;
use AIArmada\Addressing\Geography\Greece\GreeceGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GreeceGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('administrative_region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Greek tree with state links', function (): void {
    $this->seedCountry('GR');

    $result = app(SeedCountryGeographiesAction::class)->execute('GR');
    $country = AddressCountry::query()->where('iso2', 'GR')->firstOrFail();

    expect($result['seeded'])->toContain('GR')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'administrative_region')->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14)
        ->and(State::query()->where('country_id', $country->id)->where('code', '69')->exists())->toBeTrue()
        ->and(State::query()->where('country_id', $country->id)->where('code', '13')->exists())->toBeFalse();
});

it('formats Greek addresses with the spaced postcode left of the locality', function (): void {
    $formatted = app(GreeceAddressFormatter::class)->format(AddressData::from([
        'line1' => '1, D. GOUNARI STREET',
        'city' => 'MAROUSI',
        'postcode' => '151 24',
        'country_code' => 'GR',
    ]));

    expect($formatted)->toBe("1, D. GOUNARI STREET\n151 24 MAROUSI\nGreece");
});
it('formats Greek post box addresses with the box postcode', function (): void {
    $formatted = app(GreeceAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. BOX 999',
        'city' => 'MAROUSI',
        'postcode' => '151 10',
        'country_code' => 'GR',
    ]));

    expect($formatted)->toBe("P.O. BOX 999\n151 10 MAROUSI\nGreece");
});
