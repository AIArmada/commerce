<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressAreaWriter;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressCountry;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seedCountry('MY');
    $this->action = app(AddressAreaWriter::class);
});

it('saves a top-level area with derived fields', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $area = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]));

    expect($area->country_code)->toBe('MY')
        ->and($area->level)->toBe(1)
        ->and($area->slug)->toBe('selangor')
        ->and($area->source)->toBe('manual')
        ->and($area->source_id)->toStartWith('my-state-selangor-');
});

it('derives child level and parent source id', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
        'source_id' => 'state-selangor',
    ]));

    $district = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'district',
        'name' => 'Petaling',
    ]));

    expect($district->level)->toBe(2)
        ->and($district->parent_id)->toBe($state->id)
        ->and($district->parent_source_id)->toBe('state-selangor');
});

it('rejects a parent from another country', function (): void {
    $malaysia = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $otherCountry = AddressCountry::query()->create([
        'iso2' => 'ZZ',
        'name' => 'Testland',
    ]);
    $parent = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $otherCountry->id,
        'type' => 'state',
        'name' => 'Central',
    ]));

    expect(fn (): string => $this->action->handle([
        'country_id' => $malaysia->id,
        'parent_id' => $parent->id,
        'type' => 'district',
        'name' => 'Petaling',
    ]))->toThrow(ValidationException::class);
});

it('rejects hierarchy cycles when updating an area', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]));
    $district = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'district',
        'name' => 'Petaling',
    ]));

    expect(fn (): string => $this->action->handle([
        'parent_id' => $district->id,
    ], (string) $state->getKey()))->toThrow(ValidationException::class);
});

it('defaults new areas to active but honors an explicit flag', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $defaulted = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]));

    $deactivated = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Perak',
        'is_active' => false,
    ]));

    expect($defaulted->is_active)->toBeTrue()
        ->and($deactivated->is_active)->toBeFalse();
});

it('honors explicit activation updates and keeps the stored value when omitted', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $area = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]));

    expect(AddressArea::query()->findOrFail($this->action->handle(['is_active' => false], (string) $area->getKey()))->is_active)->toBeFalse()
        ->and(AddressArea::query()->findOrFail($this->action->handle(['name' => 'Selangor Darul Ehsan'], (string) $area->getKey()))->is_active)->toBeFalse()
        ->and(AddressArea::query()->findOrFail($this->action->handle(['is_active' => true], (string) $area->getKey()))->is_active)->toBeTrue();
});

it('moves source-owned containment links when the parent changes without a new hierarchy type', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $first = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
        'source_id' => 'state-selangor',
    ]));
    $second = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Perak',
        'source_id' => 'state-perak',
    ]));
    $area = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $first->id,
        'type' => 'locality',
        'name' => 'Bangsar',
        'hierarchy_type' => 'postal',
    ]));

    $moved = AddressArea::query()->findOrFail($this->action->handle(['parent_id' => $second->id], (string) $area->getKey()));

    expect($moved->parent_id)->toBe($second->id)
        ->and($moved->parent_source_id)->toBe('state-perak');

    $links = AddressAreaRelationship::query()->where('child_address_area_id', $area->id)->get();

    expect($links)->toHaveCount(1)
        ->and($links->first()->parent_address_area_id)->toBe($second->id)
        ->and($links->first()->hierarchy_type)->toBe('postal')
        ->and($links->first()->relationship_type)->toBe('contains');
});

it('removes source-owned containment links and the parent pointer on detach', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
        'source_id' => 'state-selangor',
    ]));
    $area = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'locality',
        'name' => 'Bangsar',
        'hierarchy_type' => 'postal',
    ]));

    expect($area->parent_source_id)->toBe('state-selangor');

    $detached = AddressArea::query()->findOrFail($this->action->handle(['parent_id' => null], (string) $area->getKey()));

    expect($detached->parent_id)->toBeNull()
        ->and($detached->parent_source_id)->toBeNull()
        ->and(AddressAreaRelationship::query()->where('child_address_area_id', $area->id)->exists())->toBeFalse();
});

it('preserves unrelated relationship sources and types when the parent changes', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $first = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]));
    $second = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Perak',
    ]));
    $area = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $first->id,
        'type' => 'locality',
        'name' => 'Bangsar',
        'hierarchy_type' => 'postal',
    ]));

    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $first->getKey(),
        'child_address_area_id' => $area->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
        'source' => 'external',
    ]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $first->getKey(),
        'child_address_area_id' => $area->getKey(),
        'relationship_type' => 'served_by',
        'hierarchy_type' => 'postal',
        'source' => 'manual',
    ]);

    $this->action->handle(['parent_id' => $second->id], (string) $area->getKey());

    $links = AddressAreaRelationship::query()->where('child_address_area_id', $area->id)->get();

    $owned = $links->first(static fn ($link): bool => $link->source === 'manual' && $link->relationship_type === 'contains');

    expect($links)->toHaveCount(3)
        ->and($owned?->parent_address_area_id)->toBe($second->id)
        ->and($links->firstWhere('source', 'external')?->parent_address_area_id)->toBe($first->id)
        ->and($links->firstWhere('relationship_type', 'served_by')?->parent_address_area_id)->toBe($first->id);
});

it('leaves relationships untouched when the parent does not change', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]));
    $area = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'locality',
        'name' => 'Bangsar',
        'hierarchy_type' => 'postal',
    ]));

    $this->action->handle(['name' => 'Bangsar Baru'], (string) $area->getKey());

    $links = AddressAreaRelationship::query()->where('child_address_area_id', $area->id)->get();

    expect($links)->toHaveCount(1)
        ->and($links->first()->parent_address_area_id)->toBe($state->id)
        ->and($links->first()->hierarchy_type)->toBe('postal');
});

it('replaces the source-owned relationship when changing hierarchy type', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
        'source_id' => 'state-selangor',
    ]));
    $area = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'locality',
        'name' => 'Bangsar',
        'hierarchy_type' => 'postal',
    ]));

    $this->action->handle([
        'hierarchy_type' => 'administrative',
    ], (string) $area->getKey());

    expect(AddressAreaRelationship::query()->where('child_address_area_id', $area->id)->count())->toBe(1)
        ->and(AddressAreaRelationship::query()->where('child_address_area_id', $area->id)->value('hierarchy_type'))->toBe('administrative');
});

it('rejects invalid area coordinates and non-boolean activation values atomically', function (array $invalid): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $area = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
        'latitude' => 3.1,
        'longitude' => 101.5,
        'is_active' => true,
    ]));

    expect(fn (): string => $this->action->handle(
        ['name' => 'Changed'] + $invalid,
        (string) $area->getKey(),
    ))->toThrow(ValidationException::class);

    $area->refresh();

    expect($area->name)->toBe('Selangor')
        ->and((float) $area->latitude)->toBe(3.1)
        ->and((float) $area->longitude)->toBe(101.5)
        ->and($area->is_active)->toBeTrue();
})->with([
    'latitude out of range' => [['latitude' => 90.01]],
    'longitude out of range' => [['longitude' => -180.01]],
    'non-finite latitude' => [['latitude' => INF]],
    'non-numeric longitude' => [['longitude' => 'invalid']],
    'non-boolean activation' => [['is_active' => 'false']],
]);

it('accepts coordinate boundaries and clears explicitly empty coordinates', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $area = AddressArea::query()->findOrFail($this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
        'latitude' => '-90',
        'longitude' => '180',
    ]));

    expect((float) $area->latitude)->toBe(-90.0)
        ->and((float) $area->longitude)->toBe(180.0);

    $this->action->handle(['latitude' => null, 'longitude' => ''], (string) $area->getKey());

    $area->refresh();

    expect($area->latitude)->toBeNull()
        ->and($area->longitude)->toBeNull();
});
