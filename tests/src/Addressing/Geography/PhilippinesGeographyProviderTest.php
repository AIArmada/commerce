<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Philippines\PhilippinesGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PhilippinesGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Filipino tree with state links', function (): void {
    $this->seedCountry('PH');

    $result = app(SeedCountryGeographiesAction::class)->execute('PH');
    $country = AddressCountry::query()->where('iso2', 'PH')->firstOrFail();

    expect($result['seeded'])->toContain('PH')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(99)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(82)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(82)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(82);

    $area = AddressArea::query()->where('source_id', 'ph:province:samar')->firstOrFail();

    expect(AddressAreaName::query()
        ->where('address_area_id', $area->getKey())
        ->where('name', 'Western Samar')
        ->exists())->toBeTrue();
});
