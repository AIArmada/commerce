<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanAddressFormatter;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(KyrgyzstanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Kyrgyz tree with state links', function (): void {
    $this->seedCountry('KG');

    $result = app(SeedCountryGeographiesAction::class)->execute('KG');
    $country = AddressCountry::query()->where('iso2', 'KG')->firstOrFail();

    expect($result['seeded'])->toContain('KG')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});

it('formats Kyrgyz addresses with the postcode left of the locality', function (): void {
    $formatted = app(KyrgyzstanAddressFormatter::class)->format(AddressData::from([
        'line1' => '193, Avenue Chuy, apt. 28',
        'city' => 'BISHKEK',
        'postcode' => '720001',
        'country_code' => 'KG',
    ]));

    expect($formatted)->toBe("193, Avenue Chuy, apt. 28\n720001 BISHKEK\nKyrgyzstan");
});
it('formats rural Kyrgyz addresses with the region below the postcode line', function (): void {
    $formatted = app(KyrgyzstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lenin Street 12',
        'city' => 'KARAKOL',
        'state' => 'Issyk-Kul',
        'postcode' => '721600',
        'country_code' => 'KG',
    ]));

    expect($formatted)->toBe("Lenin Street 12\n721600 KARAKOL\nIssyk-Kul\nKyrgyzstan");
});
