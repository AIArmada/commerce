<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SearchAddressAreasAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Models\AddressCountry;

beforeEach(function (): void {
    $country = AddressCountry::query()->create(['iso2' => 'MY', 'name' => 'Malaysia']);

    $kl = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'wilayah_persekutuan',
        'level' => 1,
        'name' => 'Wilayah Persekutuan Kuala Lumpur',
        'slug' => 'wilayah-persekutuan-kuala-lumpur',
        'source' => 'test-fixture',
        'source_id' => 'fixture:kl',
        'is_active' => true,
    ]);
    AddressAreaName::query()->create([
        'address_area_id' => $kl->getKey(),
        'name' => 'KL',
        'source' => 'test-fixture',
        'name_type' => 'abbreviation',
        'is_preferred' => false,
    ]);

    $wangsa = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'parent_id' => $kl->getKey(),
        'type' => 'locality',
        'level' => 2,
        'name' => 'Wangsa Maju',
        'slug' => 'wangsa-maju',
        'source' => 'test-fixture',
        'source_id' => 'fixture:wangsa-maju',
        'is_active' => true,
    ]);
    AddressAreaRole::query()->create([
        'address_area_id' => $wangsa->getKey(),
        'role' => 'postal_locality',
        'source' => 'test-fixture',
        'country_code' => 'MY',
        'is_primary' => true,
    ]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $kl->getKey(),
        'child_address_area_id' => $wangsa->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'postal',
        'source' => 'test-fixture',
    ]);

    $johor = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Johor',
        'slug' => 'johor',
        'source' => 'test-fixture',
        'source_id' => 'fixture:johor',
        'is_active' => true,
    ]);
    $jb = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'parent_id' => $johor->getKey(),
        'type' => 'district',
        'level' => 2,
        'name' => 'Johor Bahru',
        'slug' => 'johor-bahru',
        'source' => 'test-fixture',
        'source_id' => 'fixture:johor-bahru',
        'is_active' => true,
    ]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $johor->getKey(),
        'child_address_area_id' => $jb->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
        'source' => 'test-fixture',
    ]);
});

it('searches localities by name and role', function (): void {
    $results = app(SearchAddressAreasAction::class)->execute(
        query: 'Wangsa',
        countryCode: 'MY',
        role: 'postal_locality',
    );

    expect($results->pluck('name')->all())->toContain('Wangsa Maju')
        ->and($results->first()->roles()->where('role', 'postal_locality')->exists())->toBeTrue();
});

it('searches area aliases', function (): void {
    $results = app(SearchAddressAreasAction::class)->execute(query: 'KL', countryCode: 'MY');

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Wilayah Persekutuan Kuala Lumpur');
});

it('finds localities under a postal parent', function (): void {
    $kl = AddressArea::query()->where('name', 'Wilayah Persekutuan Kuala Lumpur')->firstOrFail();

    $results = app(SearchAddressAreasAction::class)->execute(
        query: 'Wangsa',
        countryCode: 'MY',
        parentId: $kl->getKey(),
        hierarchyType: 'postal',
    );

    expect($results->pluck('name')->all())->toContain('Wangsa Maju');
});

it('keeps administrative relationships separate from postal relationships', function (): void {
    $district = AddressArea::query()
        ->where('country_code', 'MY')
        ->where('type', 'district')
        ->firstOrFail();

    $results = app(SearchAddressAreasAction::class)->execute(
        query: $district->name,
        countryCode: 'MY',
        hierarchyType: 'administrative',
    );

    expect($district->ancestors()->wherePivot('hierarchy_type', 'administrative')->exists())->toBeTrue()
        ->and($results->pluck('id')->all())->toContain($district->getKey());
});

it('excludes other-hierarchy branches but keeps root areas', function (): void {
    $admin = app(SearchAddressAreasAction::class)->execute(
        query: 'Wangsa',
        countryCode: 'MY',
        hierarchyType: 'administrative',
    );

    $roots = app(SearchAddressAreasAction::class)->execute(
        query: 'Kuala Lumpur',
        countryCode: 'MY',
        hierarchyType: 'administrative',
    );

    expect($admin->pluck('name')->all())->not->toContain('Wangsa Maju')
        ->and($roots->pluck('name')->all())->toContain('Wilayah Persekutuan Kuala Lumpur');
});

it('does not cross hierarchy branches when filtering by parent', function (): void {
    $state = AddressArea::query()
        ->where('country_code', 'MY')
        ->where('type', 'state')
        ->where('name', 'Johor')
        ->firstOrFail();

    $results = app(SearchAddressAreasAction::class)->execute(
        query: 'Johor Bahru',
        countryCode: 'MY',
        parentId: $state->getKey(),
        hierarchyType: 'postal',
    );

    $unfiltered = app(SearchAddressAreasAction::class)->execute(
        query: 'Johor Bahru',
        countryCode: 'MY',
    );

    expect($results->pluck('name')->all())->not->toContain('Johor Bahru')
        ->and($unfiltered->pluck('name')->all())->toContain('Johor Bahru');
});

it('finds a cross-boundary area under either administrative parent', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $firstParent = AddressArea::query()->where('source_id', 'fixture:johor')->firstOrFail();
    $secondParent = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Second State',
        'slug' => 'second-state',
        'source' => 'test-fixture',
        'source_id' => 'fixture:second-state',
        'is_active' => true,
    ]);
    $child = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'parent_id' => $firstParent->getKey(),
        'type' => 'mukim',
        'level' => 3,
        'name' => 'Split Mukim',
        'slug' => 'split-mukim',
        'source' => 'test-fixture',
        'source_id' => 'fixture:split-mukim',
        'is_active' => true,
    ]);

    foreach ([$firstParent, $secondParent] as $parent) {
        AddressAreaRelationship::query()->create([
            'parent_address_area_id' => $parent->getKey(),
            'child_address_area_id' => $child->getKey(),
            'relationship_type' => 'contains',
            'hierarchy_type' => 'administrative',
            'source' => 'test-fixture',
        ]);
    }

    $underFirst = app(SearchAddressAreasAction::class)->execute(
        query: 'Split Mukim',
        countryCode: 'MY',
        parentId: $firstParent->getKey(),
        hierarchyType: 'administrative',
    );
    $underSecond = app(SearchAddressAreasAction::class)->execute(
        query: 'Split Mukim',
        countryCode: 'MY',
        parentId: $secondParent->getKey(),
        hierarchyType: 'administrative',
    );

    expect($underFirst->pluck('name')->all())->toContain('Split Mukim')
        ->and($underSecond->pluck('name')->all())->toContain('Split Mukim');
});
