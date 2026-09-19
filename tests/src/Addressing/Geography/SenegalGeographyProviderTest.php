<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Senegal\SenegalAddressFormatter;
use AIArmada\Addressing\Geography\Senegal\SenegalGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SenegalGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Senegalese tree with state links', function (): void {
    $this->seedCountry('SN');

    $result = app(SeedCountryGeographiesAction::class)->execute('SN');
    $country = AddressCountry::query()->where('iso2', 'SN')->firstOrFail();

    expect($result['seeded'])->toContain('SN')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});

it('formats Senegalese addresses with the postcode left of the office', function (): void {
    $formatted = app(SenegalAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 AVENUE CHEIKH ANTA DIOP',
        'city' => 'DAKAR',
        'postcode' => '12500',
        'country_code' => 'SN',
    ]));

    expect($formatted)->toBe("12 AVENUE CHEIKH ANTA DIOP\n12500 DAKAR\nSenegal");
});
it('prints matching Senegalese city and region once', function (): void {
    $formatted = app(SenegalAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1534',
        'city' => 'Ziguinchor',
        'state' => 'Ziguinchor',
        'postcode' => '27000',
        'country_code' => 'SN',
    ]));

    expect($formatted)->toBe("BP 1534\n27000 Ziguinchor\nSenegal");
});
