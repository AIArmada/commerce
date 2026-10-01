<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\AddressAreaHierarchyResolver;

beforeEach(function (): void {
    $country = AddressCountry::query()->create(['iso2' => 'MY', 'name' => 'Malaysia']);

    $this->country = $country;
    $this->root = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Selangor',
        'slug' => 'selangor',
        'source' => 'test-fixture',
        'source_id' => 'fixture:selangor',
        'is_active' => true,
    ]);

    $this->activeChild = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'city',
        'level' => 2,
        'name' => 'Shah Alam',
        'slug' => 'shah-alam',
        'source' => 'test-fixture',
        'source_id' => 'fixture:shah-alam',
        'is_active' => true,
    ]);

    $this->inactiveChild = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'city',
        'level' => 2,
        'name' => 'Shah Alam',
        'slug' => 'shah-alam-stale',
        'source' => 'test-fixture',
        'source_id' => 'fixture:shah-alam-stale',
        'is_active' => false,
    ]);

    foreach ([$this->activeChild, $this->inactiveChild] as $child) {
        AddressAreaRelationship::query()->create([
            'parent_address_area_id' => $this->root->getKey(),
            'child_address_area_id' => $child->getKey(),
            'relationship_type' => 'contains',
            'hierarchy_type' => 'administrative',
            'source' => 'test-fixture',
        ]);
        AddressAreaRole::query()->create([
            'address_area_id' => $child->getKey(),
            'role' => 'municipality',
            'source' => 'test-fixture',
            'country_code' => 'MY',
            'is_primary' => true,
        ]);
    }
});

it('resolves the active candidate when an inactive duplicate shares its name', function (): void {
    $resolved = app(AddressAreaHierarchyResolver::class)->resolveWithinHierarchy(
        name: 'Shah Alam',
        countryId: $this->country->id,
        hierarchyRootId: $this->root->getKey(),
        hierarchyType: 'administrative',
        types: ['city'],
    );

    expect($resolved?->getKey())->toBe($this->activeChild->getKey());
});

it('returns null when only an inactive candidate matches', function (): void {
    $this->activeChild->update(['is_active' => false]);

    $resolved = app(AddressAreaHierarchyResolver::class)->resolveWithinHierarchy(
        name: 'Shah Alam',
        countryId: $this->country->id,
        hierarchyRootId: $this->root->getKey(),
        hierarchyType: 'administrative',
        types: ['city'],
    );

    expect($resolved)->toBeNull();
});

it('keeps ambiguity semantics across two active candidates', function (): void {
    $this->inactiveChild->update(['is_active' => true]);

    $resolved = app(AddressAreaHierarchyResolver::class)->resolveWithinHierarchy(
        name: 'Shah Alam',
        countryId: $this->country->id,
        hierarchyRootId: $this->root->getKey(),
        hierarchyType: 'administrative',
        types: ['city'],
    );

    expect($resolved)->toBeNull();
});

it('resolves roles through the active candidate only', function (): void {
    $resolver = app(AddressAreaHierarchyResolver::class);

    $resolved = $resolver->resolveRoleWithinHierarchy(
        name: 'Shah Alam',
        countryId: $this->country->id,
        hierarchyRootId: $this->root->getKey(),
        hierarchyType: 'administrative',
        role: 'municipality',
    );

    expect($resolved?->getKey())->toBe($this->activeChild->getKey());

    $this->activeChild->update(['is_active' => false]);

    expect($resolver->resolveRoleWithinHierarchy(
        name: 'Shah Alam',
        countryId: $this->country->id,
        hierarchyRootId: $this->root->getKey(),
        hierarchyType: 'administrative',
        role: 'municipality',
    ))->toBeNull();
});

it('excludes inactive ancestors from ancestor walks', function (): void {
    $mid = AddressArea::query()->create([
        'country_id' => $this->country->id,
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Petaling',
        'slug' => 'petaling',
        'source' => 'test-fixture',
        'source_id' => 'fixture:petaling',
        'is_active' => false,
    ]);
    $leaf = AddressArea::query()->create([
        'country_id' => $this->country->id,
        'country_code' => 'MY',
        'type' => 'mukim',
        'level' => 3,
        'name' => 'Damansara',
        'slug' => 'damansara',
        'source' => 'test-fixture',
        'source_id' => 'fixture:damansara',
        'is_active' => true,
    ]);

    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $this->root->getKey(),
        'child_address_area_id' => $mid->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
        'source' => 'test-fixture',
    ]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $mid->getKey(),
        'child_address_area_id' => $leaf->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
        'source' => 'test-fixture',
    ]);

    $resolver = app(AddressAreaHierarchyResolver::class);

    expect($resolver->ancestorsOf($leaf, 'administrative')->pluck('id')->all())
        ->not->toContain($mid->getKey())
        ->and($resolver->ancestorOfTypes($leaf, ['district'], 'administrative'))->toBeNull();
});
