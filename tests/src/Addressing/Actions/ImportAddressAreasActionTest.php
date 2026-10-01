<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;

beforeEach(function (): void {
    $this->seedCountry('MY');
    $this->action = app(ImportAddressAreasAction::class);
});

it('imports valid hierarchy', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
    ]);

    $result = $this->action->execute($source);

    expect($result->created)->toBe(1);
    expect($result->hasFailures())->toBeFalse();

    $area = AddressArea::where('source', 'test')->where('source_id', '1')->first();
    expect($area)->not->toBeNull();
    expect($area->name)->toBe('Selangor');
    expect($area->slug)->toBe('selangor');
});

it('dry-run creates nothing', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
    ]);

    $result = $this->action->execute($source, dryRun: true);

    expect($result->created)->toBe(0);
    expect($result->skipped)->toBe(1);

    $area = AddressArea::where('source', 'test')->where('source_id', '1')->first();
    expect($area)->toBeNull();
});

it('fails missing country', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'XX',
            type: 'state',
            name: 'Unknown',
        ),
    ]);

    $result = $this->action->execute($source);

    expect($result->created)->toBe(0);
    expect($result->hasFailures())->toBeTrue();
    expect($result->failures[0]->reason)->toContain('Country not found');
});

it('fails missing parent', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '2',
            countryCode: 'MY',
            type: 'district',
            name: 'Petaling',
            parentSourceId: 'nonexistent',
        ),
    ]);

    $result = $this->action->execute($source);

    expect($result->created)->toBe(0);
    expect($result->hasFailures())->toBeTrue();
    expect($result->failures[0]->reason)->toContain('Parent not found');
});

it('upserts by source and source_id', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
    ]);

    $this->action->execute($source);

    $source2 = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor Updated',
        ),
    ]);

    $result = $this->action->execute($source2);

    expect($result->created)->toBe(0);
    expect($result->updated)->toBe(1);

    $area = AddressArea::where('source', 'test')->where('source_id', '1')->first();
    expect($area->name)->toBe('Selangor Updated');
});

it('records source_payload and synced_at', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
            sourcePayload: ['external_id' => 'SGR-001'],
        ),
    ]);

    $this->action->execute($source);

    $area = AddressArea::where('source', 'test')->where('source_id', '1')->first();
    expect($area->source_payload)->toBe(['external_id' => 'SGR-001']);
    expect($area->synced_at)->not->toBeNull();
});

it('fails when updating an area would create a hierarchy cycle', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: 'root',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
        new AddressAreaData(
            source: 'test',
            sourceId: 'child',
            countryCode: 'MY',
            type: 'district',
            name: 'Petaling',
            parentSourceId: 'root',
        ),
    ]);

    $this->action->execute($source);

    $result = $this->action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: 'root',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
            parentSourceId: 'child',
        ),
    ]));

    expect($result->created)->toBe(0);
    expect($result->updated)->toBe(0);
    expect($result->hasFailures())->toBeTrue();
    expect($result->failures[0]->reason)->toContain('cycle');

    $root = AddressArea::where('source', 'test')->where('source_id', 'root')->first();
    expect($root->parent_id)->toBeNull();
});

it('removes stale source-owned relationships when hierarchy metadata changes', function (): void {
    $this->action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'root', countryCode: 'MY', type: 'state', name: 'Selangor'),
        new AddressAreaData(source: 'test', sourceId: 'child', countryCode: 'MY', type: 'district', name: 'Petaling', parentSourceId: 'root', hierarchyType: 'administrative'),
    ]));

    $this->action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'child', countryCode: 'MY', type: 'district', name: 'Petaling', hierarchyType: null),
    ]));

    expect(AddressAreaRelationship::query()->where('source', 'test')->exists())->toBeFalse();
});

it('keeps a manual relationship when an import owns the same typed edge', function (): void {
    $this->action->execute(new ArrayAddressAreaSource('feed', [
        new AddressAreaData(source: 'feed', sourceId: 'root', countryCode: 'MY', type: 'state', name: 'Selangor'),
        new AddressAreaData(source: 'feed', sourceId: 'child', countryCode: 'MY', type: 'district', name: 'Petaling', parentSourceId: 'root', hierarchyType: 'administrative'),
    ]));

    $areas = AddressArea::query()->where('source', 'feed')->get()->keyBy('source_id');
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $areas['root']->getKey(),
        'child_address_area_id' => $areas['child']->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
        'source' => 'manual',
    ]);

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $areas['root']->getKey())
        ->where('child_address_area_id', $areas['child']->getKey())
        ->pluck('source')
        ->sort()
        ->values()
        ->all())->toBe(['feed', 'manual']);

    $this->action->execute(new ArrayAddressAreaSource('feed', [
        new AddressAreaData(source: 'feed', sourceId: 'child', countryCode: 'MY', type: 'district', name: 'Petaling', hierarchyType: null),
    ]));

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $areas['root']->getKey())
        ->where('child_address_area_id', $areas['child']->getKey())
        ->pluck('source')
        ->all())->toBe(['manual']);
});

it('quiesces when re-imported without changes', function (): void {
    $rows = fn (): array => [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
    ];

    $this->action->execute(new ArrayAddressAreaSource('test', $rows()));
    $result = $this->action->execute(new ArrayAddressAreaSource('test', $rows()));

    expect($result->created)->toBe(0);
    expect($result->updated)->toBe(0);
    expect($result->skipped)->toBe(1);
    expect($result->hasFailures())->toBeFalse();
});

it('preserves operator deactivation unless reactivate is set', function (): void {
    $rows = fn (): array => [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
    ];

    $this->action->execute(new ArrayAddressAreaSource('test', $rows()));
    AddressArea::where('source', 'test')->where('source_id', '1')->update(['is_active' => false]);

    $this->action->execute(new ArrayAddressAreaSource('test', $rows()));

    expect(AddressArea::where('source', 'test')->where('source_id', '1')->firstOrFail()->is_active)->toBeFalse();

    $this->action->execute(new ArrayAddressAreaSource('test', $rows()), reactivate: true);

    expect(AddressArea::where('source', 'test')->where('source_id', '1')->firstOrFail()->is_active)->toBeTrue();
});

it('reports missing parents during dry-run', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '2',
            countryCode: 'MY',
            type: 'district',
            name: 'Petaling',
            parentSourceId: 'nonexistent',
        ),
    ]);

    $result = $this->action->execute($source, dryRun: true);

    expect($result->created)->toBe(0);
    expect($result->hasFailures())->toBeTrue();
    expect($result->failures[0]->reason)->toContain('Parent not found');
});

it('fails rows that exceed address_areas column limits instead of throwing', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: str_repeat('s', 256),
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
        new AddressAreaData(
            source: 'test',
            sourceId: '2',
            countryCode: 'MY',
            type: 'state',
            name: str_repeat('n', 256),
        ),
    ]);

    $result = $this->action->execute($source);

    expect($result->created)->toBe(0);
    expect($result->hasFailures())->toBeTrue();
    expect($result->failures)->toHaveCount(2);
    expect($result->failures[0]->reason)->toContain('exceeds 255 characters: sourceId');
    expect($result->failures[1]->reason)->toContain('exceeds 255 characters: name');
    expect(AddressArea::query()->where('source', 'test')->exists())->toBeFalse();
});

it('fails a cycle routed through rows staged in the same import', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'a', countryCode: 'MY', type: 'state', name: 'A', level: 1),
        new AddressAreaData(source: 'test', sourceId: 'b', countryCode: 'MY', type: 'district', name: 'B', parentSourceId: 'a', level: 2),
        new AddressAreaData(source: 'test', sourceId: 'c', countryCode: 'MY', type: 'mukim', name: 'C', parentSourceId: 'b', level: 3),
        new AddressAreaData(source: 'test', sourceId: 'a', countryCode: 'MY', type: 'state', name: 'A', parentSourceId: 'c', level: 1),
    ]);

    $result = $this->action->execute($source);

    expect($result->created)->toBe(3)
        ->and($result->hasFailures())->toBeTrue()
        ->and($result->failures)->toHaveCount(1)
        ->and($result->failures[0]->sourceId)->toBe('a')
        ->and($result->failures[0]->reason)->toContain('cycle');

    $root = AddressArea::where('source', 'test')->where('source_id', 'a')->firstOrFail();
    expect($root->parent_id)->toBeNull();
});

it('fails a staged self-parent on a repeated source row', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'a', countryCode: 'MY', type: 'state', name: 'A'),
        new AddressAreaData(source: 'test', sourceId: 'a', countryCode: 'MY', type: 'state', name: 'A', parentSourceId: 'a'),
    ]);

    $result = $this->action->execute($source);

    expect($result->created)->toBe(1)
        ->and($result->hasFailures())->toBeTrue()
        ->and($result->failures[0]->reason)->toContain('cannot be the current area');

    expect(AddressArea::where('source', 'test')->where('source_id', 'a')->firstOrFail()->parent_id)->toBeNull();
});

it('rejects a foreign-country parent for new rows', function (): void {
    AddressCountry::query()->create(['iso2' => 'ZZ', 'name' => 'Testland']);

    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'root', countryCode: 'MY', type: 'state', name: 'Selangor'),
        new AddressAreaData(source: 'test', sourceId: 'child', countryCode: 'ZZ', type: 'district', name: 'Petaling', parentSourceId: 'root'),
    ]);

    $result = $this->action->execute($source);

    expect($result->created)->toBe(1)
        ->and($result->hasFailures())->toBeTrue()
        ->and($result->failures[0]->sourceId)->toBe('child')
        ->and($result->failures[0]->reason)->toContain('same country')
        ->and(AddressArea::query()->where('source', 'test')->where('source_id', 'child')->exists())->toBeFalse();
});

it('rejects a foreign-country parent when updating an existing row', function (): void {
    AddressCountry::query()->create(['iso2' => 'ZZ', 'name' => 'Testland']);

    $this->action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'root', countryCode: 'MY', type: 'state', name: 'Selangor'),
        new AddressAreaData(source: 'test', sourceId: 'child', countryCode: 'MY', type: 'district', name: 'Petaling', parentSourceId: 'root'),
    ]));

    $result = $this->action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'child', countryCode: 'ZZ', type: 'district', name: 'Petaling', parentSourceId: 'root'),
    ]));

    expect($result->updated)->toBe(0)
        ->and($result->hasFailures())->toBeTrue()
        ->and($result->failures[0]->reason)->toContain('same country')
        ->and(AddressArea::query()->where('source', 'test')->where('source_id', 'child')->firstOrFail()->country_code)->toBe('MY');
});

it('rejects inverted and equal parent levels for new rows', function (): void {
    $inverted = $this->action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'root', countryCode: 'MY', type: 'state', name: 'Selangor', level: 2),
        new AddressAreaData(source: 'test', sourceId: 'child', countryCode: 'MY', type: 'district', name: 'Petaling', parentSourceId: 'root', level: 1),
    ]));

    expect($inverted->created)->toBe(1)
        ->and($inverted->hasFailures())->toBeTrue()
        ->and($inverted->failures[0]->reason)->toContain('lower level');

    $equal = $this->action->execute(new ArrayAddressAreaSource('equal', [
        new AddressAreaData(source: 'equal', sourceId: 'root', countryCode: 'MY', type: 'state', name: 'Selangor', level: 1),
        new AddressAreaData(source: 'equal', sourceId: 'child', countryCode: 'MY', type: 'district', name: 'Petaling', parentSourceId: 'root', level: 1),
    ]));

    expect($equal->created)->toBe(1)
        ->and($equal->hasFailures())->toBeTrue()
        ->and($equal->failures[0]->reason)->toContain('lower level');
});

it('rejects an inverted parent level when updating an existing row', function (): void {
    $this->action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'root', countryCode: 'MY', type: 'state', name: 'Selangor', level: 1),
        new AddressAreaData(source: 'test', sourceId: 'tall', countryCode: 'MY', type: 'division', name: 'Tall', level: 5),
        new AddressAreaData(source: 'test', sourceId: 'child', countryCode: 'MY', type: 'district', name: 'Petaling', parentSourceId: 'root', level: 2),
    ]));

    $result = $this->action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'child', countryCode: 'MY', type: 'district', name: 'Petaling', parentSourceId: 'tall', level: 2),
    ]));

    expect($result->updated)->toBe(0)
        ->and($result->hasFailures())->toBeTrue()
        ->and($result->failures[0]->reason)->toContain('lower level');

    $child = AddressArea::query()->where('source', 'test')->where('source_id', 'child')->firstOrFail();
    $root = AddressArea::query()->where('source', 'test')->where('source_id', 'root')->firstOrFail();
    expect($child->parent_id)->toBe($root->getKey());
});

it('keeps the last duplicate source row', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'a', countryCode: 'MY', type: 'state', name: 'First'),
        new AddressAreaData(source: 'test', sourceId: 'a', countryCode: 'MY', type: 'state', name: 'Second'),
    ]);

    $result = $this->action->execute($source);

    expect($result->created)->toBe(1)
        ->and($result->updated)->toBe(1)
        ->and($result->hasFailures())->toBeFalse()
        ->and(AddressArea::query()->where('source', 'test')->where('source_id', 'a')->firstOrFail()->name)->toBe('Second');
});

it('dry-run validates children of parents staged earlier in the payload', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'root', countryCode: 'MY', type: 'state', name: 'Selangor', level: 1),
        new AddressAreaData(source: 'test', sourceId: 'child', countryCode: 'MY', type: 'district', name: 'Petaling', parentSourceId: 'root', level: 2),
    ]);

    $result = $this->action->execute($source, dryRun: true);

    expect($result->hasFailures())->toBeFalse()
        ->and($result->created)->toBe(0)
        ->and($result->skipped)->toBe(2)
        ->and(AddressArea::query()->where('source', 'test')->exists())->toBeFalse();
});

it('dry-run reports the same failures as a real import without writing', function (): void {
    $rows = fn (): array => [
        new AddressAreaData(source: 'test', sourceId: 'root', countryCode: 'MY', type: 'state', name: 'Selangor', level: 1),
        new AddressAreaData(source: 'test', sourceId: 'child', countryCode: 'MY', type: 'district', name: 'Petaling', parentSourceId: 'root', level: 2),
        new AddressAreaData(source: 'test', sourceId: 'orphan', countryCode: 'MY', type: 'district', name: 'Orphan', parentSourceId: 'missing'),
        new AddressAreaData(source: 'test', sourceId: 'root', countryCode: 'MY', type: 'state', name: 'Selangor', parentSourceId: 'child', level: 1),
    ];

    $dry = $this->action->execute(new ArrayAddressAreaSource('test', $rows()), dryRun: true);

    expect(AddressArea::query()->where('source', 'test')->exists())->toBeFalse();

    $real = $this->action->execute(new ArrayAddressAreaSource('test', $rows()));

    $reasons = fn ($result): array => array_map(
        static fn ($failure): string => $failure->sourceId . ':' . $failure->reason,
        $result->failures,
    );

    expect($reasons($dry))->toBe($reasons($real))
        ->and($dry->hasFailures())->toBeTrue()
        ->and($real->created)->toBe(2)
        ->and(AddressArea::query()->where('source', 'test')->count())->toBe(2);
});

it('truncates derived slugs to the slug column limit', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: str_repeat('æ', 255),
        ),
    ]);

    $result = $this->action->execute($source);

    expect($result->hasFailures())->toBeFalse();

    $area = AddressArea::where('source', 'test')->where('source_id', '1')->firstOrFail();
    expect(mb_strlen($area->slug))->toBeLessThanOrEqual(255);
});

it('rejects a new child attaching to an already-cyclic branch', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $a = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'state',
        'level' => null,
        'name' => 'Area A',
        'slug' => 'area-a',
        'is_active' => true,
        'source' => 'test',
        'source_id' => 'a',
    ]);
    $b = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'district',
        'level' => null,
        'name' => 'Area B',
        'slug' => 'area-b',
        'is_active' => true,
        'source' => 'test',
        'source_id' => 'b',
    ]);
    $a->update(['parent_id' => $b->getKey()]);
    $b->update(['parent_id' => $a->getKey()]);

    $result = $this->action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'x', countryCode: 'MY', type: 'mukim', name: 'Area X', parentSourceId: 'a', level: null),
    ]));

    expect($result->created)->toBe(0)
        ->and($result->hasFailures())->toBeTrue()
        ->and($result->failures[0]->reason)->toContain('cycle')
        ->and(AddressArea::query()->where('source', 'test')->where('source_id', 'x')->exists())->toBeFalse();
});

it('rejects reparenting an existing root onto an already-cyclic branch', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $a = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'state',
        'level' => null,
        'name' => 'Area A',
        'slug' => 'area-a',
        'is_active' => true,
        'source' => 'test',
        'source_id' => 'a',
    ]);
    $b = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'district',
        'level' => null,
        'name' => 'Area B',
        'slug' => 'area-b',
        'is_active' => true,
        'source' => 'test',
        'source_id' => 'b',
    ]);
    $a->update(['parent_id' => $b->getKey()]);
    $b->update(['parent_id' => $a->getKey()]);
    AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'mukim',
        'level' => null,
        'name' => 'Area X',
        'slug' => 'area-x',
        'is_active' => true,
        'source' => 'test',
        'source_id' => 'x',
    ]);

    $result = $this->action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'x', countryCode: 'MY', type: 'mukim', name: 'Area X', parentSourceId: 'a', level: null),
    ]));

    expect($result->updated)->toBe(0)
        ->and($result->hasFailures())->toBeTrue()
        ->and($result->failures[0]->reason)->toContain('cycle')
        ->and(AddressArea::query()->where('source', 'test')->where('source_id', 'x')->firstOrFail()->parent_id)->toBeNull();
});

it('dry-run reports cyclic-branch failures without writing', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $a = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'state',
        'level' => null,
        'name' => 'Area A',
        'slug' => 'area-a',
        'is_active' => true,
        'source' => 'test',
        'source_id' => 'a',
    ]);
    $b = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'district',
        'level' => null,
        'name' => 'Area B',
        'slug' => 'area-b',
        'is_active' => true,
        'source' => 'test',
        'source_id' => 'b',
    ]);
    $a->update(['parent_id' => $b->getKey()]);
    $b->update(['parent_id' => $a->getKey()]);
    AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'mukim',
        'level' => null,
        'name' => 'Area X',
        'slug' => 'area-x',
        'is_active' => true,
        'source' => 'test',
        'source_id' => 'x',
    ]);

    $result = $this->action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(source: 'test', sourceId: 'new-child', countryCode: 'MY', type: 'mukim', name: 'New Child', parentSourceId: 'a', level: null),
        new AddressAreaData(source: 'test', sourceId: 'x', countryCode: 'MY', type: 'mukim', name: 'Area X', parentSourceId: 'a', level: null),
    ]), dryRun: true);

    expect($result->hasFailures())->toBeTrue()
        ->and($result->failures)->toHaveCount(2)
        ->and($result->failures[0]->reason)->toContain('cycle')
        ->and($result->failures[1]->reason)->toContain('cycle')
        ->and(AddressArea::query()->where('source', 'test')->where('source_id', 'new-child')->exists())->toBeFalse()
        ->and(AddressArea::query()->where('source', 'test')->where('source_id', 'x')->firstOrFail()->parent_id)->toBeNull();
});
