<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Croatia\CroatiaAddressFormatter;
use AIArmada\Addressing\Geography\Croatia\CroatiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CroatiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Croatian tree with state links', function (): void {
    $this->seedCountry('HR');

    $result = app(SeedCountryGeographiesAction::class)->execute('HR');
    $country = AddressCountry::query()->where('iso2', 'HR')->firstOrFail();

    expect($result['seeded'])->toContain('HR')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(20)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(20);
});

it('formats Croatian inbound addresses with the HR postcode prefix', function (): void {
    $formatted = app(CroatiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Krapinska 17/I stan 4',
        'city' => 'ZAGREB',
        'postcode' => 'HR-10000',
        'country_code' => 'HR',
    ]));

    expect($formatted)->toBe("Krapinska 17/I stan 4\nHR-10000 ZAGREB\nCroatia");
});
it('formats Croatian domestic addresses with a bare postcode', function (): void {
    $formatted = app(CroatiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.P. 105',
        'city' => 'SPLIT',
        'postcode' => '21001',
        'country_code' => 'HR',
    ]));

    expect($formatted)->toBe("P.P. 105\n21001 SPLIT\nCroatia");
});
