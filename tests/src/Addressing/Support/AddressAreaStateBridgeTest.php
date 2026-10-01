<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\AddressAreaStateBridge;
use AIArmada\Addressing\Support\GeographyReferenceCleanup;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seedCountry('MY');
});

it('bridges a state to its level one area and back from a child area', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'name' => 'WP Kuala Lumpur',
        'label' => 'Wilayah Persekutuan Kuala Lumpur',
    ]);
    $area = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Kuala Lumpur',
        'slug' => 'kuala-lumpur',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    AddressAreaStateLink::query()->create([
        'address_area_id' => $area->id,
        'state_id' => $state->id,
    ]);
    $district = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Central',
        'slug' => 'central',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $area->id,
        'child_address_area_id' => $district->id,
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]);
    expect(AddressAreaStateBridge::areaIdForState($state))->toBe($area->id)
        ->and(AddressAreaStateBridge::stateIdForArea($district))->toBe($state->id);
});

it('does not resolve an inactive area through the state bridge', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create(['country_id' => $country->id, 'name' => 'Selangor', 'label' => 'Selangor']);
    $area = AddressArea::query()->create([
        'country_id' => $country->id, 'country_code' => 'MY', 'type' => 'state', 'level' => 1,
        'name' => 'Old Selangor', 'slug' => 'old-selangor', 'source' => 'test',
        'source_id' => Str::uuid()->toString(), 'is_active' => false,
    ]);
    AddressAreaStateLink::query()->create(['address_area_id' => $area->id, 'state_id' => $state->id]);

    expect(AddressAreaStateBridge::stateIdForArea($area))->toBeNull();
});

it('reverse-resolves through active typed relationships without a legacy parent id', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'name' => 'Johor',
        'label' => 'Johor',
    ]);
    $area = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Johor',
        'slug' => 'johor',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    $child = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Johor Bahru',
        'slug' => 'johor-bahru',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    AddressAreaStateLink::query()->create(['address_area_id' => $area->id, 'state_id' => $state->id]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $area->id,
        'child_address_area_id' => $child->id,
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]);

    expect(AddressAreaStateBridge::stateIdForArea($child))->toBe($state->id);
});

it('ignores inactive state-area links and chooses the current active root', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'name' => 'Selangor',
        'label' => 'Selangor',
    ]);
    $inactive = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Old Selangor',
        'slug' => 'old-selangor',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
        'is_active' => false,
    ]);
    $active = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Selangor',
        'slug' => 'selangor',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    AddressAreaStateLink::query()->create(['address_area_id' => $inactive->id, 'state_id' => $state->id]);
    AddressAreaStateLink::query()->create(['address_area_id' => $active->id, 'state_id' => $state->id]);

    expect(AddressAreaStateBridge::areaIdForState($state))->toBe($active->id)
        ->and(AddressAreaStateBridge::stateIdForArea($inactive))->toBeNull();
});

it('does not return a stale area mapping after the state is deleted in the same request', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'code' => 'SGR',
        'name' => 'Selangor',
    ]);
    $area = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Selangor',
        'slug' => 'selangor-stale',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    AddressAreaStateLink::query()->create([
        'address_area_id' => $area->id,
        'state_id' => $state->id,
    ]);
    $stateId = (string) $state->getKey();

    // Prime both hierarchy variants of the forward lookup.
    expect(AddressAreaStateBridge::areaIdForState($stateId))->toBe($area->id)
        ->and(AddressAreaStateBridge::areaIdForState($stateId, 'administrative'))->toBe($area->id);

    $state->delete();

    expect(AddressAreaStateBridge::areaIdForState($stateId))->toBeNull()
        ->and(AddressAreaStateBridge::areaIdForState($stateId, 'administrative'))->toBeNull();
});

it('does not return a deleted state through the reverse lookup after the state is deleted', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'code' => 'JHR',
        'name' => 'Johor',
    ]);
    $area = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Johor',
        'slug' => 'johor-stale',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    $child = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Johor Bahru',
        'slug' => 'johor-bahru-stale',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    AddressAreaStateLink::query()->create(['address_area_id' => $area->id, 'state_id' => $state->id]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $area->id,
        'child_address_area_id' => $child->id,
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]);

    // Prime only the reverse lookup.
    expect(AddressAreaStateBridge::stateIdForArea($child))->toBe($state->id);

    $state->delete();

    expect(AddressAreaStateBridge::stateIdForArea($child))->toBeNull()
        ->and(AddressAreaStateBridge::stateIdForArea($area))->toBeNull();
});

it('does not return obsolete mappings after a provider prune in the same request', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'code' => 'PRN',
        'name' => 'Stale Province',
    ]);
    $area = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Stale Province',
        'slug' => 'stale-province',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    AddressAreaStateLink::query()->create(['address_area_id' => $area->id, 'state_id' => $state->id]);
    $stateId = (string) $state->getKey();

    expect(AddressAreaStateBridge::areaIdForState($stateId))->toBe($area->id)
        ->and(AddressAreaStateBridge::stateIdForArea($area))->toBe($stateId);

    expect(GeographyReferenceCleanup::pruneStatesByCodes($country, ['PRN']))->toBe(1);

    expect(AddressAreaStateBridge::areaIdForState($stateId))->toBeNull()
        ->and(AddressAreaStateBridge::stateIdForArea($area))->toBeNull();
});
