<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\SeedFacilityTypesAction;
use AIArmada\Events\Actions\SyncVenueFacilitiesAction;
use AIArmada\Events\Enums\FacilityAvailability;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttribute;
use AIArmada\Events\Models\EventFacility;
use AIArmada\Events\Models\FacilityType;
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Models\VenueFacility;
use AIArmada\Events\Models\VenueSpace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function createVenueFacilityType(array $attributes = []): FacilityType
{
    return FacilityType::factory()->create($attributes);
}

function createVenueFacilityCatalog(): array
{
    $venue = Venue::factory()->create();
    $space = VenueSpace::factory()->create(['venue_id' => $venue->id]);
    $type = createVenueFacilityType();

    return [$venue, $space, $type];
}

it('creates a venue-wide facility without a space', function (): void {
    [$venue, , $type] = createVenueFacilityCatalog();

    $facility = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => null,
        'facility_type_id' => $type->id,
        'availability' => FacilityAvailability::Available,
        'visibility' => 'public',
    ]);

    expect($facility->venue_id)->toBe($venue->id)
        ->and($facility->venue_space_id)->toBeNull()
        ->and($facility->availability)->toBe(FacilityAvailability::Available)
        ->and($venue->facilities()->whereKey($facility)->exists())->toBeTrue();
});

it('creates a space-bound facility when the venue matches the space', function (): void {
    [$venue, $space, $type] = createVenueFacilityCatalog();

    $facility = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => $space->id,
        'facility_type_id' => $type->id,
    ]);

    expect($facility->venue_id)->toBe($venue->id)
        ->and($facility->venue_space_id)->toBe($space->id)
        ->and($space->facilities()->whereKey($facility)->exists())->toBeTrue()
        ->and($venue->facilities()->whereKey($facility)->exists())->toBeTrue();
});

it('creates a standalone template facility without a venue', function (): void {
    $template = VenueSpace::factory()->create(['venue_id' => null]);
    $type = createVenueFacilityType();

    $facility = VenueFacility::factory()->create([
        'venue_id' => null,
        'venue_space_id' => $template->id,
        'facility_type_id' => $type->id,
    ]);

    expect($facility->venue_id)->toBeNull()
        ->and($facility->venue_space_id)->toBe($template->id)
        ->and($template->facilities()->whereKey($facility)->exists())->toBeTrue();
});

it('rejects a facility with no place', function (): void {
    $type = createVenueFacilityType();

    expect(fn (): VenueFacility => VenueFacility::factory()->create([
        'venue_id' => null,
        'venue_space_id' => null,
        'facility_type_id' => $type->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects nonexistent place and catalog ids', function (): void {
    [$venue, $space, $type] = createVenueFacilityCatalog();
    $missing = (string) Str::uuid();

    expect(fn (): VenueFacility => VenueFacility::factory()->create([
        'venue_id' => $missing,
        'facility_type_id' => $type->id,
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn (): VenueFacility => VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => $missing,
        'facility_type_id' => $type->id,
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn (): VenueFacility => VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => $space->id,
        'facility_type_id' => $missing,
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn (): VenueFacility => VenueFacility::factory()->create([
        'venue_space_id' => $space->id,
        'facility_type_id' => $missing,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a facility when the venue does not match the space', function (): void {
    [$venueA, $spaceA] = createVenueFacilityCatalog();
    [$venueB] = createVenueFacilityCatalog();
    $type = createVenueFacilityType();
    $template = VenueSpace::factory()->create(['venue_id' => null]);

    expect(fn (): VenueFacility => VenueFacility::factory()->create([
        'venue_id' => $venueB->id,
        'venue_space_id' => $spaceA->id,
        'facility_type_id' => $type->id,
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn (): VenueFacility => VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => $template->id,
        'facility_type_id' => $type->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects mismatched place ids on update', function (): void {
    [$venueA, $spaceA, $type] = createVenueFacilityCatalog();
    [$venueB, $spaceB] = createVenueFacilityCatalog();

    // Each rejection starts from its own persisted valid row so a failed
    // attempt can never leak mutated in-memory state into the next case.
    $spaceMismatch = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => $spaceA->id,
        'facility_type_id' => $type->id,
    ]);

    expect(function () use ($spaceMismatch, $spaceB): void {
        $spaceMismatch->venue_space_id = $spaceB->id;
        $spaceMismatch->save();
    })->toThrow(InvalidArgumentException::class);

    expect($spaceMismatch->refresh()->venue_space_id)->toBe($spaceA->id);

    $venueMismatch = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => $spaceA->id,
        'facility_type_id' => $type->id,
    ]);

    expect(function () use ($venueMismatch, $venueB): void {
        $venueMismatch->venue_id = $venueB->id;
        $venueMismatch->save();
    })->toThrow(InvalidArgumentException::class);

    expect($venueMismatch->refresh()->venue_id)->toBe($venueA->id)
        ->and($venueMismatch->venue_space_id)->toBe($spaceA->id);

    $catalogMismatch = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => $spaceA->id,
        'facility_type_id' => $type->id,
    ]);

    expect(function () use ($catalogMismatch): void {
        $catalogMismatch->facility_type_id = (string) Str::uuid();
        $catalogMismatch->save();
    })->toThrow(InvalidArgumentException::class);

    expect($catalogMismatch->refresh()->facility_type_id)->toBe($type->id);
});

it('rejects mismatched place ids on relationship writes', function (): void {
    [$venueA, $spaceA] = createVenueFacilityCatalog();
    [$venueB, $spaceB] = createVenueFacilityCatalog();
    $type = createVenueFacilityType();

    expect(fn (): VenueFacility => $venueA->facilities()->create([
        'venue_space_id' => $spaceB->id,
        'facility_type_id' => $type->id,
        'visibility' => 'public',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn (): VenueFacility => $spaceA->facilities()->create([
        'venue_id' => $venueB->id,
        'facility_type_id' => $type->id,
        'visibility' => 'public',
    ]))->toThrow(InvalidArgumentException::class);
});

it('inherits the venue when creating through a space relation', function (): void {
    [, $space] = createVenueFacilityCatalog();
    $type = createVenueFacilityType();
    $template = VenueSpace::factory()->create(['venue_id' => null]);

    $bound = $space->facilities()->create([
        'facility_type_id' => $type->id,
        'visibility' => 'public',
    ]);
    $templated = $template->facilities()->create([
        'facility_type_id' => $type->id,
        'visibility' => 'public',
    ]);

    expect($bound->venue_space_id)->toBe($space->id)
        ->and($bound->venue_id)->toBe($space->venue_id)
        ->and($templated->venue_space_id)->toBe($template->id)
        ->and($templated->venue_id)->toBeNull();
});

it('casts availability to a typed enum with an available default', function (): void {
    [$venue, , $type] = createVenueFacilityCatalog();

    $facility = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'facility_type_id' => $type->id,
        'availability' => FacilityAvailability::Unavailable,
    ]);

    expect($facility->availability)->toBe(FacilityAvailability::Unavailable);

    $defaulted = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'facility_type_id' => $type->id,
        'availability' => null,
    ]);

    expect($defaulted->refresh()->availability)->toBe(FacilityAvailability::Available);

    expect(fn (): VenueFacility => VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'facility_type_id' => $type->id,
        'availability' => 'bogus',
    ]))->toThrow(ValueError::class);
});

it('seeds the default facility catalog idempotently', function (): void {
    $first = app(SeedFacilityTypesAction::class)->execute();

    expect($first['created'])->toBe(5)
        ->and(FacilityType::query()->whereIn('code', [
            'parking', 'parking_oku', 'aircond', 'wheelchair_access', 'wudu_area',
        ])->count())->toBe(5);

    $second = app(SeedFacilityTypesAction::class)->execute();

    expect($second['created'])->toBe(0)
        ->and(FacilityType::query()->count())->toBe(5);
});

it('keeps app customization and unrelated rows when reseeding', function (): void {
    app(SeedFacilityTypesAction::class)->execute();

    FacilityType::query()->where('code', 'parking')->update(['name' => 'App Parking Name']);

    $custom = createVenueFacilityType(['code' => 'app_custom_room']);

    $result = app(SeedFacilityTypesAction::class)->execute();

    expect($result['created'])->toBe(0)
        ->and(FacilityType::query()->where('code', 'parking')->value('name'))->toBe('App Parking Name')
        ->and(FacilityType::query()->whereKey($custom)->exists())->toBeTrue()
        ->and(FacilityType::query()->count())->toBe(6);
});

it('filters facilities by place, type, and availability', function (): void {
    [$venueA, $spaceA] = createVenueFacilityCatalog();
    [$venueB] = createVenueFacilityCatalog();
    $parking = createVenueFacilityType(['code' => 'filter_parking']);
    $wudu = createVenueFacilityType(['code' => 'filter_wudu']);

    $venueWide = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => null,
        'facility_type_id' => $parking->id,
        'availability' => FacilityAvailability::Available,
    ]);
    $spaceBound = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => $spaceA->id,
        'facility_type_id' => $wudu->id,
        'availability' => FacilityAvailability::Unavailable,
    ]);
    $otherVenue = VenueFacility::factory()->create([
        'venue_id' => $venueB->id,
        'facility_type_id' => $parking->id,
    ]);

    expect(VenueFacility::query()->forVenue($venueA)->pluck('id')->all())
        ->toEqualCanonicalizing([$venueWide->id, $spaceBound->id])
        ->and(VenueFacility::query()->forSpace($spaceA)->pluck('id')->all())->toBe([$spaceBound->id])
        ->and(VenueFacility::query()->forVenue($venueA)->venueWide()->pluck('id')->all())->toBe([$venueWide->id])
        ->and(VenueFacility::query()->forFacilityType($parking)->pluck('id')->all())
        ->toEqualCanonicalizing([$venueWide->id, $otherVenue->id])
        ->and(VenueFacility::query()->whereAvailability(FacilityAvailability::Unavailable)->pluck('id')->all())
        ->toBe([$spaceBound->id])
        ->and(VenueFacility::query()->whereTypeCode('filter_wudu')->pluck('id')->all())
        ->toBe([$spaceBound->id]);
});

it('lists active facility types in catalog order', function (): void {
    createVenueFacilityType(['code' => 'zzz_last', 'sort_order' => 99]);
    createVenueFacilityType(['code' => 'aaa_first', 'sort_order' => 1]);
    createVenueFacilityType(['code' => 'inactive_type', 'sort_order' => 0, 'is_active' => false]);

    expect(FacilityType::query()->active()->ordered()->pluck('code')->all())
        ->toBe(['aaa_first', 'zzz_last']);
});

it('removes only the venue facilities when a venue is deleted', function (): void {
    [$venueA, $spaceA, $type] = createVenueFacilityCatalog();
    [$venueB] = createVenueFacilityCatalog();
    $template = VenueSpace::factory()->create(['venue_id' => null]);

    $venueWide = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'facility_type_id' => $type->id,
    ]);
    $spaceBound = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => $spaceA->id,
        'facility_type_id' => $type->id,
    ]);
    $otherVenue = VenueFacility::factory()->create([
        'venue_id' => $venueB->id,
        'facility_type_id' => $type->id,
    ]);
    $templated = VenueFacility::factory()->create([
        'venue_id' => null,
        'venue_space_id' => $template->id,
        'facility_type_id' => $type->id,
    ]);

    $venueA->delete();

    expect(VenueFacility::query()->whereKey($venueWide)->exists())->toBeFalse()
        ->and(VenueFacility::query()->whereKey($spaceBound)->exists())->toBeFalse()
        ->and(VenueFacility::query()->whereKey($otherVenue)->exists())->toBeTrue()
        ->and(VenueFacility::query()->whereKey($templated)->exists())->toBeTrue()
        ->and(FacilityType::query()->whereKey($type)->exists())->toBeTrue();
});

it('removes only the space facilities when a space is deleted', function (): void {
    [$venue, $space, $type] = createVenueFacilityCatalog();

    $spaceBound = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => $space->id,
        'facility_type_id' => $type->id,
    ]);
    $venueWide = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => null,
        'facility_type_id' => $type->id,
    ]);

    $space->delete();

    expect(VenueFacility::query()->whereKey($spaceBound)->exists())->toBeFalse()
        ->and(VenueFacility::query()->whereKey($venueWide)->exists())->toBeTrue()
        ->and(Venue::query()->whereKey($venue)->exists())->toBeTrue();
});

it('blocks type deletion when another owner references it from an event', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    [$venue, , $type] = createVenueFacilityCatalog();

    $placeFacility = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'facility_type_id' => $type->id,
    ]);

    $eventFacility = OwnerContext::withOwner($ownerA, function () use ($type): EventFacility {
        $event = Event::factory()->create();

        return EventFacility::factory()->create([
            'event_id' => $event->id,
            'facility_type_id' => $type->id,
        ]);
    });

    expect(fn (): mixed => OwnerContext::withOwner($ownerB, fn (): mixed => $type->delete()))
        ->toThrow(InvalidArgumentException::class, 'is referenced by event facilities');

    expect(FacilityType::query()->whereKey($type)->exists())->toBeTrue()
        ->and(VenueFacility::query()->whereKey($placeFacility)->exists())->toBeTrue()
        ->and(OwnerContext::withOwner($ownerA, fn (): bool => EventFacility::query()->whereKey($eventFacility)->exists()))
        ->toBeTrue();
});

it('blocks type deletion from explicit global context when events reference it', function (): void {
    $owner = User::factory()->create();
    [$venue, , $type] = createVenueFacilityCatalog();

    $placeFacility = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'facility_type_id' => $type->id,
    ]);

    $eventFacility = OwnerContext::withOwner($owner, function () use ($type): EventFacility {
        $event = Event::factory()->create();

        return EventFacility::factory()->create([
            'event_id' => $event->id,
            'facility_type_id' => $type->id,
        ]);
    });

    expect(fn (): mixed => OwnerContext::withOwner(null, fn (): mixed => $type->delete()))
        ->toThrow(InvalidArgumentException::class, 'is_active=false');

    expect(FacilityType::query()->whereKey($type)->exists())->toBeTrue()
        ->and(VenueFacility::query()->whereKey($placeFacility)->exists())->toBeTrue()
        ->and(OwnerContext::withOwner(null, fn (): bool => EventFacility::query()->withoutOwnerScope()->whereKey($eventFacility)->exists()))
        ->toBeTrue();
});

it('removes only the deleted type place values when no event uses the type', function (): void {
    [$venueA, $spaceA, $typeA] = createVenueFacilityCatalog();
    [$venueB] = createVenueFacilityCatalog();
    $typeB = createVenueFacilityType();

    $doomedWide = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => null,
        'facility_type_id' => $typeA->id,
    ]);
    $doomedSpace = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => $spaceA->id,
        'facility_type_id' => $typeA->id,
    ]);
    $doomedOtherVenue = VenueFacility::factory()->create([
        'venue_id' => $venueB->id,
        'facility_type_id' => $typeA->id,
    ]);
    $keptType = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'facility_type_id' => $typeB->id,
    ]);
    $keptOtherVenue = VenueFacility::factory()->create([
        'venue_id' => $venueB->id,
        'facility_type_id' => $typeB->id,
    ]);

    $typeA->delete();

    expect(VenueFacility::query()->whereKey($doomedWide)->exists())->toBeFalse()
        ->and(VenueFacility::query()->whereKey($doomedSpace)->exists())->toBeFalse()
        ->and(VenueFacility::query()->whereKey($doomedOtherVenue)->exists())->toBeFalse()
        ->and(VenueFacility::query()->whereKey($keptType)->exists())->toBeTrue()
        ->and(VenueFacility::query()->whereKey($keptOtherVenue)->exists())->toBeTrue()
        ->and(FacilityType::query()->whereKey($typeA)->exists())->toBeFalse()
        ->and(FacilityType::query()->whereKey($typeB)->exists())->toBeTrue()
        ->and(Venue::query()->whereKey($venueA)->exists())->toBeTrue()
        ->and(Venue::query()->whereKey($venueB)->exists())->toBeTrue();
});

it('runs facility crud against remapped tables without hardcoded leaks', function (): void {
    $defaults = [
        'venues' => config('events.database.tables.venues'),
        'venue_spaces' => config('events.database.tables.venue_spaces'),
        'facility_types' => config('events.database.tables.facility_types'),
        'venue_facilities' => config('events.database.tables.venue_facilities'),
    ];

    config()->set('events.database.tables.venues', 'remap_venues');
    config()->set('events.database.tables.venue_spaces', 'remap_venue_spaces');
    config()->set('events.database.tables.facility_types', 'remap_facility_types');
    config()->set('events.database.tables.venue_facilities', 'remap_venue_facilities');

    expect((new Venue)->getTable())->toBe('remap_venues')
        ->and((new VenueSpace)->getTable())->toBe('remap_venue_spaces')
        ->and((new FacilityType)->getTable())->toBe('remap_facility_types')
        ->and((new VenueFacility)->getTable())->toBe('remap_venue_facilities');

    $migrations = __DIR__ . '/../../../packages/events/database/migrations';

    foreach ([
        '2000_01_01_000004_create_event_venues_table.php',
        '2000_01_01_000005_create_event_venue_spaces_table.php',
        '2000_01_01_000008_create_event_facility_types_table.php',
        '2000_01_01_000009_create_event_venue_facilities_table.php',
    ] as $file) {
        $migration = require $migrations . '/' . $file;
        $migration->up();
    }

    [$venue, $space, $type] = createVenueFacilityCatalog();

    $wide = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => null,
        'facility_type_id' => $type->id,
        'quantity' => 400,
    ]);
    $bound = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => $space->id,
        'facility_type_id' => $type->id,
    ]);

    $wide->update(['quantity' => 450]);

    expect($wide->refresh()->quantity)->toBe(450)
        ->and(VenueFacility::query()->forVenue($venue)->venueWide()->pluck('id')->all())->toBe([$wide->id])
        ->and(VenueFacility::query()->forSpace($space)->pluck('id')->all())->toBe([$bound->id]);

    $space->delete();

    expect(VenueFacility::query()->whereKey($bound)->exists())->toBeFalse()
        ->and(VenueFacility::query()->whereKey($wide)->exists())->toBeTrue();

    $venue->delete();

    expect(VenueFacility::query()->count())->toBe(0)
        ->and(FacilityType::query()->whereKey($type)->exists())->toBeTrue();

    // Every write above hit the remapped tables; the default tables stayed empty.
    expect(DB::table($defaults['venues'])->count())->toBe(0)
        ->and(DB::table($defaults['venue_spaces'])->count())->toBe(0)
        ->and(DB::table($defaults['facility_types'])->count())->toBe(0)
        ->and(DB::table($defaults['venue_facilities'])->count())->toBe(0);
});

it('keeps place facilities separate from event attributes and event facilities', function (): void {
    $owner = User::factory()->create();
    [$venue, , $type] = createVenueFacilityCatalog();

    $placeFacility = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'facility_type_id' => $type->id,
    ]);

    $eventType = createVenueFacilityType();

    [$attribute, $eventFacility] = OwnerContext::withOwner($owner, function () use ($eventType): array {
        $event = Event::factory()->create();

        return [
            EventAttribute::factory()->create(['event_id' => $event->id]),
            EventFacility::factory()->create([
                'event_id' => $event->id,
                'facility_type_id' => $eventType->id,
            ]),
        ];
    });

    app(SeedFacilityTypesAction::class)->execute();

    expect(VenueFacility::query()->count())->toBe(1)
        ->and(EventAttribute::query()->withoutOwnerScope()->count())->toBe(1)
        ->and($placeFacility->facility_type_id)->not->toBe($eventFacility->facility_type_id)
        ->and($attribute->attribute_key)->not->toStartWith('parking');
});

it('shares the place catalog across owner contexts', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    [$venue, $space, $type] = OwnerContext::withOwner(null, function (): array {
        $venue = Venue::factory()->create();
        $space = VenueSpace::factory()->create(['venue_id' => $venue->id]);
        $type = createVenueFacilityType();

        VenueFacility::factory()->create([
            'venue_id' => $venue->id,
            'venue_space_id' => $space->id,
            'facility_type_id' => $type->id,
        ]);

        app(SeedFacilityTypesAction::class)->execute();

        return [$venue, $space, $type];
    });

    foreach ([$ownerA, $ownerB] as $owner) {
        OwnerContext::withOwner($owner, function () use ($venue, $space, $type): void {
            expect(Venue::query()->whereKey($venue)->exists())->toBeTrue()
                ->and(VenueSpace::query()->whereKey($space)->exists())->toBeTrue()
                ->and(FacilityType::query()->whereKey($type)->exists())->toBeTrue()
                ->and(FacilityType::query()->where('code', 'wudu_area')->exists())->toBeTrue()
                ->and(VenueFacility::query()->forVenue($venue)->count())->toBe(1);
        });
    }
});

it('retains quantity, capacity, and fee fields on place facilities', function (): void {
    $space = VenueSpace::factory()->create(['venue_id' => null, 'capacity' => 120]);
    [$venue] = createVenueFacilityCatalog();
    $type = createVenueFacilityType();

    $facility = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'facility_type_id' => $type->id,
        'quantity' => 4,
        'capacity' => 200,
        'is_free' => false,
        'fee_amount' => 1500,
        'currency' => 'MYR',
    ]);

    expect($space->refresh()->capacity)->toBe(120)
        ->and($facility->refresh()->quantity)->toBe(4)
        ->and($facility->capacity)->toBe(200)
        ->and($facility->is_free)->toBeFalse()
        ->and($facility->fee_amount)->toBe(1500)
        ->and($facility->currency)->toBe('MYR');
});

it('syncs venue-wide facilities from catalog codes', function (): void {
    app(SeedFacilityTypesAction::class)->execute();
    $venue = Venue::factory()->create();

    $synced = app(SyncVenueFacilitiesAction::class)->handle($venue, [
        ['code' => 'parking', 'availability' => FacilityAvailability::Available],
        ['code' => 'wudu_area', 'availability' => 'available'],
    ]);

    expect($synced)->toBe(2)
        ->and(VenueFacility::query()->forVenue($venue)->venueWide()->count())->toBe(2);

    app(SyncVenueFacilitiesAction::class)->handle($venue, [
        ['code' => 'parking'],
    ]);

    expect(VenueFacility::query()->forVenue($venue)->venueWide()->count())->toBe(1)
        ->and(VenueFacility::query()->forVenue($venue)->whereTypeCode('parking')->exists())->toBeTrue();
});

it('rejects venue space reparenting while facilities are attached', function (): void {
    [$venueA, $spaceA, $type] = createVenueFacilityCatalog();
    [$venueB] = createVenueFacilityCatalog();
    $template = VenueSpace::factory()->create(['venue_id' => null]);

    $bound = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => $spaceA->id,
        'facility_type_id' => $type->id,
    ]);
    $templated = VenueFacility::factory()->create([
        'venue_id' => null,
        'venue_space_id' => $template->id,
        'facility_type_id' => $type->id,
    ]);

    expect(function () use ($spaceA, $venueB): void {
        $spaceA->venue_id = $venueB->id;
        $spaceA->save();
    })->toThrow(InvalidArgumentException::class, 'while facilities are attached');

    $spaceA->refresh();

    expect(function () use ($spaceA): void {
        $spaceA->venue_id = null;
        $spaceA->save();
    })->toThrow(InvalidArgumentException::class, 'while facilities are attached');

    expect(function () use ($template, $venueB): void {
        $template->venue_id = $venueB->id;
        $template->save();
    })->toThrow(InvalidArgumentException::class, 'while facilities are attached');

    expect($spaceA->refresh()->venue_id)->toBe($venueA->id)
        ->and($template->refresh()->venue_id)->toBeNull()
        ->and($bound->refresh()->venue_id)->toBe($venueA->id)
        ->and($bound->venue_space_id)->toBe($spaceA->id)
        ->and($templated->refresh()->venue_id)->toBeNull();
});

it('allows venue space reparenting when no facilities are attached', function (): void {
    [$venueA] = createVenueFacilityCatalog();
    [$venueB] = createVenueFacilityCatalog();

    $space = VenueSpace::factory()->create(['venue_id' => $venueA->id]);
    $space->venue_id = $venueB->id;
    $space->save();

    expect($space->refresh()->venue_id)->toBe($venueB->id);

    $space->venue_id = null;
    $space->save();

    expect($space->refresh()->venue_id)->toBeNull();

    $template = VenueSpace::factory()->create(['venue_id' => null]);
    $template->venue_id = $venueA->id;
    $template->save();

    expect($template->refresh()->venue_id)->toBe($venueA->id);
});

it('rejects venue spaces with unknown or malformed venue ids', function (): void {
    $missing = (string) Str::uuid();

    expect(fn (): VenueSpace => VenueSpace::factory()->create(['venue_id' => $missing]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): VenueSpace => VenueSpace::factory()->create(['venue_id' => '']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): VenueSpace => VenueSpace::factory()->create(['venue_id' => 123]))
        ->toThrow(InvalidArgumentException::class);

    [$venue] = createVenueFacilityCatalog();
    $space = VenueSpace::factory()->create(['venue_id' => $venue->id]);

    expect(function () use ($space, $missing): void {
        $space->venue_id = $missing;
        $space->save();
    })->toThrow(InvalidArgumentException::class);

    expect($space->refresh()->venue_id)->toBe($venue->id);
});

it('rejects malformed place ids instead of silently nulling them', function (): void {
    [$venue, $space, $type] = createVenueFacilityCatalog();

    foreach ([123, '', 0, false] as $bad) {
        expect(fn (): VenueFacility => VenueFacility::factory()->create([
            'venue_id' => $venue->id,
            'venue_space_id' => $bad,
            'facility_type_id' => $type->id,
        ]))->toThrow(InvalidArgumentException::class);

        expect(fn (): VenueFacility => VenueFacility::factory()->create([
            'venue_id' => $bad,
            'venue_space_id' => $space->id,
            'facility_type_id' => $type->id,
        ]))->toThrow(InvalidArgumentException::class);

        expect(fn (): VenueFacility => VenueFacility::factory()->create([
            'venue_id' => $bad,
            'facility_type_id' => $type->id,
        ]))->toThrow(InvalidArgumentException::class);
    }

    expect(VenueFacility::query()->count())->toBe(0);
});

it('rejects malformed place ids on update', function (): void {
    [$venue, , $type] = createVenueFacilityCatalog();

    $facility = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => null,
        'facility_type_id' => $type->id,
    ]);

    foreach ([123, ''] as $bad) {
        $attempt = VenueFacility::query()->whereKey($facility)->firstOrFail();
        $attempt->venue_space_id = $bad;

        expect(fn (): mixed => $attempt->save())->toThrow(InvalidArgumentException::class);
    }

    foreach ([123, ''] as $bad) {
        $attempt = VenueFacility::query()->whereKey($facility)->firstOrFail();
        $attempt->venue_id = $bad;

        expect(fn (): mixed => $attempt->save())->toThrow(InvalidArgumentException::class);
    }

    expect($facility->refresh()->venue_id)->toBe($venue->id)
        ->and($facility->venue_space_id)->toBeNull();
});

it('derives the venue from a bound space when venue id is omitted or null', function (): void {
    [, $space, $type] = createVenueFacilityCatalog();
    $template = VenueSpace::factory()->create(['venue_id' => null]);

    $omitted = VenueFacility::factory()->create([
        'venue_space_id' => $space->id,
        'facility_type_id' => $type->id,
    ]);

    $explicitNull = VenueFacility::factory()->create([
        'venue_id' => null,
        'venue_space_id' => $space->id,
        'facility_type_id' => $type->id,
    ]);

    $templated = VenueFacility::factory()->create([
        'venue_space_id' => $template->id,
        'facility_type_id' => $type->id,
    ]);

    expect($omitted->refresh()->venue_id)->toBe($space->venue_id)
        ->and($explicitNull->refresh()->venue_id)->toBe($space->venue_id)
        ->and($templated->refresh()->venue_id)->toBeNull();
});

it('updates reads and deletes every facility shape while retaining the rest', function (): void {
    [$venueA, $spaceA] = createVenueFacilityCatalog();
    [$venueB] = createVenueFacilityCatalog();
    $template = VenueSpace::factory()->create(['venue_id' => null]);
    $parking = createVenueFacilityType(['code' => 'crud_parking']);
    $wudu = createVenueFacilityType(['code' => 'crud_wudu']);

    $wide = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => null,
        'facility_type_id' => $parking->id,
        'quantity' => 400,
    ]);
    $bound = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => $spaceA->id,
        'facility_type_id' => $wudu->id,
    ]);
    $templated = VenueFacility::factory()->create([
        'venue_id' => null,
        'venue_space_id' => $template->id,
        'facility_type_id' => $parking->id,
    ]);
    $other = VenueFacility::factory()->create([
        'venue_id' => $venueB->id,
        'venue_space_id' => null,
        'facility_type_id' => $wudu->id,
    ]);

    $wide->update(['quantity' => 450, 'notes' => 'Level B2']);
    $bound->update(['availability' => FacilityAvailability::Unavailable]);
    $templated->update(['capacity' => 60]);

    expect($wide->refresh()->quantity)->toBe(450)
        ->and($wide->notes)->toBe('Level B2')
        ->and($bound->refresh()->availability)->toBe(FacilityAvailability::Unavailable)
        ->and($templated->refresh()->capacity)->toBe(60)
        ->and(VenueFacility::query()->forVenue($venueA)->count())->toBe(2)
        ->and(VenueFacility::query()->forSpace($template)->count())->toBe(1)
        ->and(VenueFacility::query()->forVenue($venueB)->count())->toBe(1);

    $bound->delete();

    expect(VenueFacility::query()->whereKey($bound)->exists())->toBeFalse()
        ->and(VenueFacility::query()->whereKey($wide)->exists())->toBeTrue()
        ->and(VenueFacility::query()->whereKey($templated)->exists())->toBeTrue()
        ->and(VenueFacility::query()->whereKey($other)->exists())->toBeTrue()
        ->and(FacilityType::query()->whereKey($parking)->exists())->toBeTrue()
        ->and(FacilityType::query()->whereKey($wudu)->exists())->toBeTrue();

    $wide->delete();
    $templated->delete();

    expect(VenueFacility::query()->whereKey($other)->exists())->toBeTrue()
        ->and(Venue::query()->whereKey($venueA)->exists())->toBeTrue()
        ->and(Venue::query()->whereKey($venueB)->exists())->toBeTrue()
        ->and(VenueSpace::query()->whereKey($template)->exists())->toBeTrue()
        ->and(FacilityType::query()->whereKey($parking)->exists())->toBeTrue();
});

it('supports catalog create read update retire and delete', function (): void {
    $type = FacilityType::factory()->create(['code' => 'catalog_room', 'name' => 'Catalog Room']);

    expect(FacilityType::query()->where('code', 'catalog_room')->firstOrFail()->id)->toBe($type->id);

    $type->update(['name' => 'Renamed Room', 'sort_order' => 7]);

    expect($type->refresh()->name)->toBe('Renamed Room')
        ->and($type->sort_order)->toBe(7);

    $type->update(['is_active' => false]);

    expect($type->refresh()->is_active)->toBeFalse()
        ->and(FacilityType::query()->active()->whereKey($type)->exists())->toBeFalse();

    $type->delete();

    expect(FacilityType::query()->whereKey($type)->exists())->toBeFalse();
});

it('rejects syncing facilities for unsaved or deleted venues', function (): void {
    app(SeedFacilityTypesAction::class)->execute();

    $unsaved = new Venue(['name' => 'Ghost', 'status' => 'active', 'visibility' => 'public']);

    expect(fn (): int => app(SyncVenueFacilitiesAction::class)->handle($unsaved, []))
        ->toThrow(InvalidArgumentException::class, 'persisted venue');

    $deleted = Venue::factory()->create();
    $deleted->delete();

    expect(fn (): int => app(SyncVenueFacilitiesAction::class)->handle($deleted, []))
        ->toThrow(InvalidArgumentException::class, 'persisted venue');

    expect(fn (): int => app(SyncVenueFacilitiesAction::class)->handle($deleted, [['code' => 'parking']]))
        ->toThrow(InvalidArgumentException::class, 'persisted venue');
});

it('removes only venue-wide rows when syncing an empty list', function (): void {
    app(SeedFacilityTypesAction::class)->execute();
    [$venueA, $spaceA] = createVenueFacilityCatalog();
    [$venueB] = createVenueFacilityCatalog();

    app(SyncVenueFacilitiesAction::class)->handle($venueA, [['code' => 'parking']]);

    $spaceRow = VenueFacility::factory()->create([
        'venue_id' => $venueA->id,
        'venue_space_id' => $spaceA->id,
        'facility_type_id' => FacilityType::query()->where('code', 'wudu_area')->firstOrFail()->id,
    ]);
    $otherVenueRow = VenueFacility::factory()->create([
        'venue_id' => $venueB->id,
        'facility_type_id' => FacilityType::query()->where('code', 'parking')->firstOrFail()->id,
    ]);

    $synced = app(SyncVenueFacilitiesAction::class)->handle($venueA, []);

    expect($synced)->toBe(0)
        ->and(VenueFacility::query()->forVenue($venueA)->venueWide()->count())->toBe(0)
        ->and(VenueFacility::query()->whereKey($spaceRow)->exists())->toBeTrue()
        ->and(VenueFacility::query()->whereKey($otherVenueRow)->exists())->toBeTrue();
});

it('does not partially replace venue facilities when sync input is invalid', function (): void {
    app(SeedFacilityTypesAction::class)->execute();
    $venue = Venue::factory()->create();

    app(SyncVenueFacilitiesAction::class)->handle($venue, [['code' => 'parking']]);

    expect(fn (): int => app(SyncVenueFacilitiesAction::class)->handle($venue, [
        ['code' => 'parking'],
        ['code' => 'nope_missing'],
    ]))->toThrow(InvalidArgumentException::class);

    expect(VenueFacility::query()->forVenue($venue)->venueWide()->count())->toBe(1);

    expect(fn (): int => app(SyncVenueFacilitiesAction::class)->handle($venue, [
        ['code' => 'wudu_area', 'availability' => 'bogus'],
    ]))->toThrow(ValueError::class);

    expect(VenueFacility::query()->forVenue($venue)->venueWide()->count())->toBe(1)
        ->and(VenueFacility::query()->forVenue($venue)->whereTypeCode('parking')->exists())->toBeTrue()
        ->and(VenueFacility::query()->forVenue($venue)->whereTypeCode('wudu_area')->exists())->toBeFalse();
});

it('rejects non-UUID string place and catalog ids on create', function (): void {
    [$venue, $space, $type] = createVenueFacilityCatalog();

    foreach (['bad-id', '123'] as $bad) {
        expect(fn (): VenueFacility => VenueFacility::factory()->create([
            'venue_id' => $bad,
            'facility_type_id' => $type->id,
        ]))->toThrow(InvalidArgumentException::class);

        expect(fn (): VenueFacility => VenueFacility::factory()->create([
            'venue_id' => $venue->id,
            'venue_space_id' => $bad,
            'facility_type_id' => $type->id,
        ]))->toThrow(InvalidArgumentException::class);

        expect(fn (): VenueFacility => VenueFacility::factory()->create([
            'venue_id' => $venue->id,
            'venue_space_id' => $space->id,
            'facility_type_id' => $bad,
        ]))->toThrow(InvalidArgumentException::class);

        expect(fn (): VenueSpace => VenueSpace::factory()->create(['venue_id' => $bad]))
            ->toThrow(InvalidArgumentException::class);
    }

    expect(VenueFacility::query()->count())->toBe(0)
        ->and(VenueSpace::query()->count())->toBe(1);
});

it('rejects non-UUID string ids on update and preserves persisted values', function (): void {
    [$venue, $space, $type] = createVenueFacilityCatalog();

    $facility = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => $space->id,
        'facility_type_id' => $type->id,
    ]);

    foreach (['bad-id', '123'] as $bad) {
        $attempt = VenueFacility::query()->whereKey($facility)->firstOrFail();
        $attempt->venue_id = $bad;

        expect(fn (): mixed => $attempt->save())->toThrow(InvalidArgumentException::class);

        $attempt = VenueFacility::query()->whereKey($facility)->firstOrFail();
        $attempt->venue_space_id = $bad;

        expect(fn (): mixed => $attempt->save())->toThrow(InvalidArgumentException::class);

        $attempt = VenueFacility::query()->whereKey($facility)->firstOrFail();
        $attempt->facility_type_id = $bad;

        expect(fn (): mixed => $attempt->save())->toThrow(InvalidArgumentException::class);
    }

    expect($facility->refresh()->venue_id)->toBe($venue->id)
        ->and($facility->venue_space_id)->toBe($space->id)
        ->and($facility->facility_type_id)->toBe($type->id);

    $ownedSpace = VenueSpace::factory()->create(['venue_id' => $venue->id]);

    foreach (['bad-id', '123'] as $bad) {
        $attempt = VenueSpace::query()->whereKey($ownedSpace)->firstOrFail();
        $attempt->venue_id = $bad;

        expect(fn (): mixed => $attempt->save())->toThrow(InvalidArgumentException::class);
    }

    expect($ownedSpace->refresh()->venue_id)->toBe($venue->id);
});

it('rejects idless models and malformed UUIDs in facility filters', function (): void {
    [$venue] = createVenueFacilityCatalog();
    $template = VenueSpace::factory()->create(['venue_id' => null]);
    $parking = createVenueFacilityType(['code' => 'scope_parking']);
    $wudu = createVenueFacilityType(['code' => 'scope_wudu']);

    $wide = VenueFacility::factory()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => null,
        'facility_type_id' => $parking->id,
    ]);
    $templated = VenueFacility::factory()->create([
        'venue_id' => null,
        'venue_space_id' => $template->id,
        'facility_type_id' => $wudu->id,
    ]);

    expect(fn (): mixed => VenueFacility::query()->forVenue(new Venue)->get())
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): mixed => VenueFacility::query()->forSpace(new VenueSpace)->get())
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): mixed => VenueFacility::query()->forFacilityType(new FacilityType)->get())
        ->toThrow(InvalidArgumentException::class);

    foreach (['bad-id', '123', ''] as $bad) {
        expect(fn (): mixed => VenueFacility::query()->forVenue($bad)->get())
            ->toThrow(InvalidArgumentException::class);
        expect(fn (): mixed => VenueFacility::query()->forSpace($bad)->get())
            ->toThrow(InvalidArgumentException::class);
        expect(fn (): mixed => VenueFacility::query()->forFacilityType($bad)->get())
            ->toThrow(InvalidArgumentException::class);
    }

    expect(fn (): mixed => VenueFacility::query()->forVenue(new Venue(['id' => 'bad-id']))->get())
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): mixed => VenueFacility::query()->forSpace(new VenueSpace(['id' => 'bad-id']))->get())
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): mixed => VenueFacility::query()->forFacilityType(new FacilityType(['id' => 'bad-id']))->get())
        ->toThrow(InvalidArgumentException::class);

    expect(VenueFacility::query()->forVenue($venue->id)->pluck('id')->all())->toBe([$wide->id])
        ->and(VenueFacility::query()->forSpace($template->id)->pluck('id')->all())->toBe([$templated->id])
        ->and(VenueFacility::query()->forFacilityType($parking->id)->pluck('id')->all())->toBe([$wide->id]);

    $missing = (string) Str::uuid();

    expect(VenueFacility::query()->forVenue($missing)->count())->toBe(0)
        ->and(VenueFacility::query()->forSpace($missing)->count())->toBe(0)
        ->and(VenueFacility::query()->forFacilityType($missing)->count())->toBe(0);
});
