<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Syria\SyriaAddressFormatter;
use AIArmada\Addressing\Geography\Syria\SyriaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SyriaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Syrian tree with state links', function (): void {
    $this->seedCountry('SY');

    $result = app(SeedCountryGeographiesAction::class)->execute('SY');
    $country = AddressCountry::query()->where('iso2', 'SY')->firstOrFail();

    expect($result['seeded'])->toContain('SY')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});

it('formats Syrian addresses without a postcode system', function (): void {
    $formatted = app(SyriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Youssef Al Azamah, no 25',
        'city' => 'DAMASCUS',
        'state' => 'Damascus',
        'country_code' => 'SY',
    ]));

    expect($formatted)->toBe("Rue Youssef Al Azamah, no 25\nDAMASCUS\nSyria");
});
it('prints any supplied Syrian code on its own line', function (): void {
    $formatted = app(SyriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Youssef Al Azamah, no 25',
        'city' => 'DAMASCUS',
        'postcode' => '0100',
        'country_code' => 'SY',
    ]));

    expect($formatted)->toBe("Rue Youssef Al Azamah, no 25\nDAMASCUS\n0100\nSyria");
});
