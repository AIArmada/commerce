<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\HongKong\HongKongAddressFormatter;
use AIArmada\Addressing\Geography\HongKong\HongKongGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(HongKongGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Hong Kong tree with state links', function (): void {
    $this->seedCountry('HK');

    $result = app(SeedCountryGeographiesAction::class)->execute('HK');
    $country = AddressCountry::query()->where('iso2', 'HK')->firstOrFail();

    expect($result['seeded'])->toContain('HK')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(18)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(18);
});

it('formats Hong Kong addresses without a postcode system', function (): void {
    $formatted = app(HongKongAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Flat 25, 12/F',
        'line2' => 'Acacia Building',
        'line3' => '150 Kennedy Road',
        'city' => 'WAN CHAI',
        'country_code' => 'HK',
    ]));

    expect($formatted)->toBe("Flat 25, 12/F\nAcacia Building\n150 Kennedy Road\nWAN CHAI\nHong Kong");
});
it('prints any forced Hong Kong code on its own line', function (): void {
    $formatted = app(HongKongAddressFormatter::class)->format(AddressData::from([
        'line1' => '150 Kennedy Road',
        'city' => 'WAN CHAI',
        'postcode' => '000',
        'country_code' => 'HK',
    ]));

    expect($formatted)->toBe("150 Kennedy Road\nWAN CHAI\n000\nHong Kong");
});
