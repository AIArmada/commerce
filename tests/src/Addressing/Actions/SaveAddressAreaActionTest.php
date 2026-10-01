<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SaveAddressAreaAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressCountry;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seedCountry('MY');
    $this->action = app(SaveAddressAreaAction::class);
});

it('saves a top-level area with derived fields', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $area = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]);

    expect($area->country_code)->toBe('MY')
        ->and($area->level)->toBe(1)
        ->and($area->slug)->toBe('selangor')
        ->and($area->source)->toBe('manual')
        ->and($area->source_id)->toStartWith('my-state-selangor-');
});

it('derives child level and parent source id', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
        'source_id' => 'state-selangor',
    ]);

    $district = $this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'district',
        'name' => 'Petaling',
    ]);

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
    $parent = $this->action->handle([
        'country_id' => $otherCountry->id,
        'type' => 'state',
        'name' => 'Central',
    ]);

    expect(fn (): AddressArea => $this->action->handle([
        'country_id' => $malaysia->id,
        'parent_id' => $parent->id,
        'type' => 'district',
        'name' => 'Petaling',
    ]))->toThrow(ValidationException::class);
});

it('rejects hierarchy cycles when updating an area', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]);
    $district = $this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'district',
        'name' => 'Petaling',
    ]);

    expect(fn (): AddressArea => $this->action->handle([
        'parent_id' => $district->id,
    ], $state))->toThrow(ValidationException::class);
});

it('defaults new areas to active but honors an explicit flag', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $defaulted = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]);

    $deactivated = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Perak',
        'is_active' => false,
    ]);

    expect($defaulted->is_active)->toBeTrue()
        ->and($deactivated->is_active)->toBeFalse();
});

it('honors explicit activation updates and keeps the stored value when omitted', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $area = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]);

    expect($this->action->handle(['is_active' => false], $area)->is_active)->toBeFalse()
        ->and($this->action->handle(['name' => 'Selangor Darul Ehsan'], $area->fresh())->is_active)->toBeFalse()
        ->and($this->action->handle(['is_active' => true], $area->fresh())->is_active)->toBeTrue();
});

it('moves source-owned containment links when the parent changes without a new hierarchy type', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $first = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
        'source_id' => 'state-selangor',
    ]);
    $second = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Perak',
        'source_id' => 'state-perak',
    ]);
    $area = $this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $first->id,
        'type' => 'locality',
        'name' => 'Bangsar',
        'hierarchy_type' => 'postal',
    ]);

    $moved = $this->action->handle(['parent_id' => $second->id], $area);

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
    $state = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
        'source_id' => 'state-selangor',
    ]);
    $area = $this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'locality',
        'name' => 'Bangsar',
        'hierarchy_type' => 'postal',
    ]);

    expect($area->parent_source_id)->toBe('state-selangor');

    $detached = $this->action->handle(['parent_id' => null], $area);

    expect($detached->parent_id)->toBeNull()
        ->and($detached->parent_source_id)->toBeNull()
        ->and(AddressAreaRelationship::query()->where('child_address_area_id', $area->id)->exists())->toBeFalse();
});

it('preserves unrelated relationship sources and types when the parent changes', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $first = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]);
    $second = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Perak',
    ]);
    $area = $this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $first->id,
        'type' => 'locality',
        'name' => 'Bangsar',
        'hierarchy_type' => 'postal',
    ]);

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

    $this->action->handle(['parent_id' => $second->id], $area);

    $links = AddressAreaRelationship::query()->where('child_address_area_id', $area->id)->get();

    $owned = $links->first(static fn ($link): bool => $link->source === 'manual' && $link->relationship_type === 'contains');

    expect($links)->toHaveCount(3)
        ->and($owned?->parent_address_area_id)->toBe($second->id)
        ->and($links->firstWhere('source', 'external')?->parent_address_area_id)->toBe($first->id)
        ->and($links->firstWhere('relationship_type', 'served_by')?->parent_address_area_id)->toBe($first->id);
});

it('leaves relationships untouched when the parent does not change', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]);
    $area = $this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'locality',
        'name' => 'Bangsar',
        'hierarchy_type' => 'postal',
    ]);

    $this->action->handle(['name' => 'Bangsar Baru'], $area);

    $links = AddressAreaRelationship::query()->where('child_address_area_id', $area->id)->get();

    expect($links)->toHaveCount(1)
        ->and($links->first()->parent_address_area_id)->toBe($state->id)
        ->and($links->first()->hierarchy_type)->toBe('postal');
});

it('replaces the source-owned relationship when changing hierarchy type', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
        'source_id' => 'state-selangor',
    ]);
    $area = $this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'locality',
        'name' => 'Bangsar',
        'hierarchy_type' => 'postal',
    ]);

    $this->action->handle([
        'hierarchy_type' => 'administrative',
    ], $area);

    expect(AddressAreaRelationship::query()->where('child_address_area_id', $area->id)->count())->toBe(1)
        ->and(AddressAreaRelationship::query()->where('child_address_area_id', $area->id)->value('hierarchy_type'))->toBe('administrative');
});
