<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Serbia\SerbiaAddressFormatter;
use AIArmada\Addressing\Geography\Serbia\SerbiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SerbiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Serbian tree with state links', function (): void {
    $this->seedCountry('RS');

    $result = app(SeedCountryGeographiesAction::class)->execute('RS');
    $country = AddressCountry::query()->where('iso2', 'RS')->firstOrFail();

    expect($result['seeded'])->toContain('RS')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(32)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(32)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(29)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(32);
});

it('formats Serbian addresses with the postcode left of the office', function (): void {
    $formatted = app(SerbiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Beogradska 3',
        'city' => 'BAJMOK',
        'postcode' => '24210',
        'country_code' => 'RS',
    ]));

    expect($formatted)->toBe("Beogradska 3\n24210 BAJMOK\nSerbia");
});
it('prints matching Serbian city and capital once', function (): void {
    $formatted = app(SerbiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Knez Mihailova 10',
        'city' => 'Belgrade',
        'state' => 'Belgrade',
        'postcode' => '11130',
        'country_code' => 'RS',
    ]));

    expect($formatted)->toBe("Knez Mihailova 10\n11130 Belgrade\nSerbia");
});
