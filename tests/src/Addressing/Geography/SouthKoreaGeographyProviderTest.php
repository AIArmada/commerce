<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SouthKoreaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the South Korean tree with state links', function (): void {
    $this->seedCountry('KR');

    $result = app(SeedCountryGeographiesAction::class)->execute('KR');
    $country = AddressCountry::query()->where('iso2', 'KR')->firstOrFail();

    expect($result['seeded'])->toContain('KR')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_city')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'metropolitan_city')->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_self_governing_province')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_self_governing_city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(17);

    $area = AddressArea::query()->where('source_id', 'kr:special_self_governing_province:jeonbuk-state')->firstOrFail();

    expect(AddressAreaName::query()
        ->where('address_area_id', $area->getKey())
        ->where('name', 'North Jeolla')
        ->exists())->toBeTrue();
});

it('formats South Korean addresses with the postcode right of the city', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro, Jung-gu',
        'state' => 'DAEGU',
        'postcode' => '42007',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro, Jung-gu\nDAEGU 42007\nSouth Korea");
});
it('prints South Korean city-states once when city and state match', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro, Jung-gu',
        'city' => 'Seoul',
        'state' => 'Seoul',
        'postcode' => '03187',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro, Jung-gu\nSeoul 03187\nSouth Korea");
});
it('keeps distinct South Korean district and city lines', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro',
        'city' => 'Jung-gu',
        'state' => 'Seoul',
        'postcode' => '04547',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro\nJung-gu\nSeoul 04547\nSouth Korea");
});
it('keeps distinct South Korean district and city lines without a postcode', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro',
        'city' => 'Jung-gu',
        'state' => 'Seoul',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro\nJung-gu\nSeoul\nSouth Korea");
});
it('formats South Korean addresses with state only and no postcode', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro, Jung-gu',
        'state' => 'Seoul',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro, Jung-gu\nSeoul\nSouth Korea");
});
it('prints South Korean city-states once without a postcode', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro, Jung-gu',
        'city' => 'Seoul',
        'state' => 'Seoul',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro, Jung-gu\nSeoul\nSouth Korea");
});
