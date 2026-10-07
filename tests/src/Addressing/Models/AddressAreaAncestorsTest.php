<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\AddressingTableResolver;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

function createLinkedAreaPair(): array
{
    $country = AddressCountry::query()->create(['iso2' => 'MY', 'name' => 'Malaysia']);

    $parent = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 1,
        'name' => 'Parent',
        'slug' => 'parent',
        'source' => 'test-fixture',
        'source_id' => 'fixture:parent',
        'is_active' => true,
    ]);

    $child = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'locality',
        'level' => 2,
        'name' => 'Child',
        'slug' => 'child',
        'source' => 'test-fixture',
        'source_id' => 'fixture:child',
        'is_active' => true,
    ]);

    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $parent->getKey(),
        'child_address_area_id' => $child->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
        'source' => 'test-fixture',
    ]);

    return [$parent, $child];
}

it('honors per-instance table overrides', function (): void {
    $area = new AddressArea;
    $area->setTable('laravel_reserved_9');

    expect($area->getTable())->toBe('laravel_reserved_9')
        ->and((new AddressArea)->getTable())->toBe(AddressingTableResolver::resolve('areas'));
});

it('filters by ancestor existence queries', function (): void {
    [$parent, $child] = createLinkedAreaPair();

    expect(AddressArea::query()->whereHas('ancestors')->pluck('name')->all())->toBe(['Child'])
        ->and(AddressArea::query()->has('ancestors')->pluck('name')->all())->toBe(['Child'])
        ->and(AddressArea::query()->doesntHave('ancestors')->pluck('name')->all())->toBe(['Parent'])
        ->and(AddressArea::query()->withCount('ancestors')->orderBy('name')->pluck('ancestors_count', 'name')->all())
        ->toBe(['Child' => 1, 'Parent' => 0])
        ->and($child->getKey())->not->toBe($parent->getKey());
});

it('loads ancestors directly', function (): void {
    [$parent, $child] = createLinkedAreaPair();

    expect($child->ancestors()->exists())->toBeTrue()
        ->and($child->ancestors()->first()?->getKey())->toBe($parent->getKey())
        ->and(AddressArea::query()->whereAncestorLink()->whereKey($child->getKey())->exists())->toBeTrue();
});

it('filters by related-area existence queries', function (): void {
    [$parent, $child] = createLinkedAreaPair();

    expect(AddressArea::query()->whereHas('relatedAreas')->pluck('name')->all())->toBe(['Parent'])
        ->and(AddressArea::query()->has('relatedAreas')->pluck('name')->all())->toBe(['Parent'])
        ->and(AddressArea::query()->doesntHave('relatedAreas')->pluck('name')->all())->toBe(['Child'])
        ->and(AddressArea::query()->withCount('relatedAreas')->orderBy('name')->pluck('related_areas_count', 'name')->all())
        ->toBe(['Child' => 0, 'Parent' => 1]);
});

it('loads related areas directly', function (): void {
    [$parent, $child] = createLinkedAreaPair();

    expect($parent->relatedAreas()->exists())->toBeTrue()
        ->and($parent->relatedAreas()->first()?->getKey())->toBe($child->getKey());
});

it('exposes self-links as plain belongsToMany relations', function (string $method): void {
    $relation = (new AddressArea)->{$method}();

    expect($relation::class)->toBe(BelongsToMany::class)
        ->and($relation->getRelationName())->toBe($method)
        ->and($relation->getPivotColumns())->toBe(['relationship_type', 'hierarchy_type', 'source', 'valid_from', 'valid_until', 'metadata']);
})->with([
    'ancestors' => ['ancestors'],
    'relatedAreas' => ['relatedAreas'],
]);
