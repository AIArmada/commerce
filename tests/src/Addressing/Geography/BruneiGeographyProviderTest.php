<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Brunei\BruneiGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all four Brunei districts', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BN')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BM',
        'name' => 'Muara',
        'label' => 'Muara',
    ]);

    app(BruneiGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Brunei-Muara')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(4)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'TE')->value('name'))->toBe('Temburong');
});

it('maps every ISO district code to its district area', function (): void {
    $mappings = app(BruneiGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(4)
        ->and(array_keys($mappings))->toBe(['BE', 'BM', 'TE', 'TU']);
});

it('defines a single administrative hierarchy down to mukims', function (): void {
    $hierarchies = app(BruneiGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('mukim')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('district');
});

it('imports the district and mukim trees with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('BN');
    $country = AddressCountry::query()->where('iso2', 'BN')->firstOrFail();

    expect($result['seeded'])->toContain('BN')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(43)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'mukim')->count())->toBe(39)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(4);

    $seria = AddressArea::query()->where('source_id', 'bn:mukim:seria')->firstOrFail();
    $belait = AddressArea::query()->where('source_id', 'bn:district:belait')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $belait->getKey())
        ->where('child_address_area_id', $seria->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue();

    $pengkalan = AddressArea::query()->where('source_id', 'bn:mukim:pengkalan-batu')->firstOrFail();

    expect($pengkalan->name)->toBe('Pengkalan Batu')
        ->and(AddressAreaName::query()
            ->where('address_area_id', $pengkalan->getKey())
            ->where('name', 'Pangkalan Batu')
            ->exists())->toBeTrue();
});
