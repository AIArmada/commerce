<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 17 South Korean states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'KR')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '11',
        'name' => 'Seoul City',
        'label' => 'Seoul City',
    ]);

    app(SouthKoreaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Seoul')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(17);
});

it('maps every South Korean state code to its area', function (): void {
    $mappings = app(SouthKoreaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(17)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['11', '26', '27', '28', '29', '30', '31', '41', '42', '43', '44', '45', '46', '47', '48', '49', '50']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SouthKoreaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the South Korean tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('KR');
    $country = AddressCountry::query()->where('iso2', 'KR')->firstOrFail();

    expect($result['seeded'])->toContain('KR')
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
