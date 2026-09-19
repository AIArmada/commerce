<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Singapore\SingaporeGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('preserves globally seeded states and adds missing Singapore districts', function (): void {
    $country = $this->seedCountry('SG');
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Central',
        'label' => 'Central',
    ]);

    app(SingaporeGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Central Singapore')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(State::query()->where('country_id', $country->id)->where('code', '05')->exists())->toBeTrue();
});

it('provides all five Singapore CDC district mappings', function (): void {
    $mappings = app(SingaporeGeographyProvider::class)->stateAreaMappings();
    $mappingCodes = array_map(
        static fn (string | int $code): string => mb_str_pad((string) $code, 2, '0', STR_PAD_LEFT),
        array_keys($mappings),
    );

    expect($mappings)->toHaveCount(5)
        ->and($mappingCodes)->toBe(['01', '02', '03', '04', '05']);
});

it('defines separate postal and administrative hierarchies', function (): void {
    $hierarchies = app(SingaporeGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(2)
        ->and($hierarchies[0]->key)->toBe('postal')
        ->and($hierarchies[0]->levels[0]->key)->toBe('postal_district')
        ->and($hierarchies[0]->levels[0]->label)->toBe('Postal District')
        ->and($hierarchies[0]->levels[1]->key)->toBe('postal_sector')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('postal_district')
        ->and($hierarchies[1]->key)->toBe('administrative')
        ->and($hierarchies[1]->levels[0]->key)->toBe('region')
        ->and($hierarchies[1]->levels[1]->key)->toBe('planning_area')
        ->and($hierarchies[1]->levels[1]->parentKey)->toBe('region');
});

it('imports the Singapore planning and postal trees with CDC state links', function (): void {
    $this->seedCountry('SG');

    $result = app(SeedCountryGeographiesAction::class)->execute('SG');
    $country = AddressCountry::query()->where('iso2', 'SG')->firstOrFail();

    expect($result['seeded'])->toContain('SG')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(174)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'planning_area')->count())->toBe(55)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'postal_sector')->count())->toBe(81)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('code', '74')->exists())->toBeFalse()
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);

    $tampines = AddressArea::query()->where('source_id', 'sg:planning-area:tampines')->firstOrFail();
    $east = AddressArea::query()->where('source_id', 'sg:region:east')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $east->getKey())
        ->where('child_address_area_id', $tampines->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue();

    $sector = AddressArea::query()->where('source_id', 'sg:postal-sector:56')->firstOrFail();
    $district = AddressArea::query()->where('source_id', 'sg:postal-district:20')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $district->getKey())
        ->where('child_address_area_id', $sector->getKey())
        ->where('hierarchy_type', 'postal')
        ->exists())->toBeTrue();

    $yishun = AddressArea::query()->where('source_id', 'sg:planning-area:yishun')->firstOrFail();

    expect(AddressAreaName::query()
        ->where('address_area_id', $yishun->getKey())
        ->where('name', 'Nee Soon')
        ->exists())->toBeTrue();
});
