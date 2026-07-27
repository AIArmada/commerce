<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SyncAddressAreaAssignmentsAction;
use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'code' => '01',
        'name' => 'Johor',
    ]);

    app(ImportAddressAreasAction::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(source: 'areas', sourceId: 'state', countryCode: 'MY', type: 'state', level: 1, name: 'Johor'),
        new AddressAreaData(source: 'areas', sourceId: 'postal', countryCode: 'MY', type: 'locality', level: 2, name: 'Bangsar', parentSourceId: 'state', hierarchyType: 'postal'),
        new AddressAreaData(source: 'areas', sourceId: 'admin', countryCode: 'MY', type: 'district', level: 2, name: 'Petaling', parentSourceId: 'state', hierarchyType: 'administrative'),
        new AddressAreaData(source: 'areas', sourceId: 'subdivision', countryCode: 'MY', type: 'mukim', level: 3, name: 'Subdivision', parentSourceId: 'admin', hierarchyType: 'administrative'),
    ]));

    $stateArea = AddressArea::query()->where('source_id', 'state')->firstOrFail();
    AddressAreaStateLink::query()->create([
        'address_area_id' => $stateArea->getKey(),
        'state_id' => $state->getKey(),
    ]);
    $this->state = $state;
});

it('syncs typed postal and administrative assignments', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $areas['postal']->getKey(),
        'administrative_district' => $areas['admin']->getKey(),
    ]);

    expect($address->areaAssignments()->count())->toBe(2)
        ->and($address->areaAssignments()->where('role', 'postal_locality')->exists())->toBeTrue()
        ->and($address->areaAssignments()->where('role', 'administrative_district')->exists())->toBeTrue();
});

it('replaces all persisted assignments when given an empty map', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $areas['postal']->getKey(),
        'administrative_district' => $areas['admin']->getKey(),
    ]);
    app(SyncAddressAreaAssignmentsAction::class)->execute($address, []);

    expect($address->areaAssignments()->exists())->toBeFalse();
});

it('rejects areas from another hierarchy level', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $admin = AddressArea::query()->where('source_id', 'admin')->firstOrFail();

    expect(fn (): mixed => app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $admin->getKey(),
    ]))->toThrow(ValidationException::class);
});

it('allows assigning a subdivision without its parent district when state is selected', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $subdivision = AddressArea::query()->where('source_id', 'subdivision')->firstOrFail();

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'administrative_subdivision' => $subdivision->getKey(),
    ]);

    expect($address->areaAssignments()->where('role', 'administrative_subdivision')->exists())->toBeTrue();
});

it('requires an active containment relationship for hierarchy assignments', function (): void {
    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $this->state->getKey(),
    ]);
    $stateArea = AddressArea::query()->where('source_id', 'state')->firstOrFail();
    $area = AddressArea::query()->create([
        'country_code' => 'MY',
        'type' => 'locality',
        'level' => 2,
        'name' => 'Unrelated locality',
        'slug' => 'unrelated-locality',
        'source' => 'areas',
        'source_id' => 'unrelated',
    ]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $stateArea->getKey(),
        'child_address_area_id' => $area->getKey(),
        'relationship_type' => 'adjacent_to',
        'hierarchy_type' => 'postal',
    ]);

    expect(fn (): mixed => app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_locality' => $area->getKey(),
    ]))->toThrow(ValidationException::class);
});

it('scopes duplicate level keys to their own hierarchy and resolves a named state root', function (): void {
    $profile = new class implements CountryAddressProfile
    {
        public function countryCode(): string
        {
            return 'MY';
        }

        public function addressHierarchies(): array
        {
            return [
                new AddressHierarchyDefinition('postal', 'Postal', [
                    new AddressLevelDefinition('province', 'Province', 'state', 'postal'),
                    new AddressLevelDefinition('district', 'Postal district', 'area', 'postal', areaTypes: ['district'], areaLevel: 2, parentKey: 'province', assignmentRole: 'postal_district'),
                    new AddressLevelDefinition('ward', 'Postal ward', 'area', 'postal', areaTypes: ['ward'], areaLevel: 3, parentKey: 'district', assignmentRole: 'postal_ward'),
                ]),
                new AddressHierarchyDefinition('administrative', 'Administrative', [
                    new AddressLevelDefinition('province', 'Province', 'state', 'administrative'),
                    new AddressLevelDefinition('district', 'Administrative district', 'area', 'administrative', areaTypes: ['district'], areaLevel: 2, parentKey: 'province', assignmentRole: 'administrative_district'),
                    new AddressLevelDefinition('ward', 'Administrative ward', 'area', 'administrative', areaTypes: ['ward'], areaLevel: 3, parentKey: 'district', assignmentRole: 'administrative_ward'),
                ]),
            ];
        }
    };
    config()->set('addressing.geography.providers', [get_class($profile)]);

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'code' => '99',
        'name' => 'Test Province',
    ]);
    $postalRoot = AddressArea::query()->create([
        'country_id' => $country->getKey(), 'country_code' => 'MY', 'type' => 'province', 'level' => 1,
        'name' => 'Postal Province', 'slug' => 'postal-province', 'source' => 'test', 'source_id' => 'postal-root',
    ]);
    $administrativeRoot = AddressArea::query()->create([
        'country_id' => $country->getKey(), 'country_code' => 'MY', 'type' => 'province', 'level' => 1,
        'name' => 'Administrative Province', 'slug' => 'administrative-province', 'source' => 'test', 'source_id' => 'administrative-root',
    ]);
    AddressAreaStateLink::query()->create(['address_area_id' => $postalRoot->getKey(), 'state_id' => $state->getKey(), 'hierarchy_type' => 'postal']);
    AddressAreaStateLink::query()->create(['address_area_id' => $administrativeRoot->getKey(), 'state_id' => $state->getKey(), 'hierarchy_type' => 'administrative']);

    $postalDistrict = AddressArea::query()->create([
        'country_id' => $country->getKey(), 'country_code' => 'MY', 'type' => 'district', 'level' => 2,
        'name' => 'Postal District', 'slug' => 'postal-district', 'source' => 'test', 'source_id' => 'postal-district',
    ]);
    $postalWard = AddressArea::query()->create([
        'country_id' => $country->getKey(), 'country_code' => 'MY', 'type' => 'ward', 'level' => 3,
        'name' => 'Postal Ward', 'slug' => 'postal-ward', 'source' => 'test', 'source_id' => 'postal-ward',
    ]);
    $administrativeDistrict = AddressArea::query()->create([
        'country_id' => $country->getKey(), 'country_code' => 'MY', 'type' => 'district', 'level' => 2,
        'name' => 'Administrative District', 'slug' => 'administrative-district', 'source' => 'test', 'source_id' => 'administrative-district',
    ]);
    $administrativeWard = AddressArea::query()->create([
        'country_id' => $country->getKey(), 'country_code' => 'MY', 'type' => 'ward', 'level' => 3,
        'name' => 'Administrative Ward', 'slug' => 'administrative-ward', 'source' => 'test', 'source_id' => 'administrative-ward',
    ]);

    foreach ([
        [$postalRoot, $postalDistrict, 'postal'],
        [$postalDistrict, $postalWard, 'postal'],
        [$administrativeRoot, $administrativeDistrict, 'administrative'],
        [$administrativeDistrict, $administrativeWard, 'administrative'],
    ] as [$parent, $child, $hierarchyType]) {
        AddressAreaRelationship::query()->create([
            'parent_address_area_id' => $parent->getKey(),
            'child_address_area_id' => $child->getKey(),
            'relationship_type' => 'contains',
            'hierarchy_type' => $hierarchyType,
            'source' => 'test',
        ]);
    }

    $address = Address::query()->create([
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'state_id' => $state->getKey(),
    ]);

    app(SyncAddressAreaAssignmentsAction::class)->execute($address, [
        'postal_district' => $postalDistrict->getKey(),
        'postal_ward' => $postalWard->getKey(),
        'administrative_district' => $administrativeDistrict->getKey(),
        'administrative_ward' => $administrativeWard->getKey(),
    ]);

    expect($address->areaAssignments()->count())->toBe(4);
});
