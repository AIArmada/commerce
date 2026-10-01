---
title: Venue Facilities
---

## Why a separate place system

Place facilities describe what a physical place offers (parking, prayer rooms,
accessibility). They are intentionally separate from two event-scoped systems:

- `EventAttribute` stores opaque per-event key/value data. It has no catalog,
  no place attachment, and no availability vocabulary.
- `EventFacility` records what an event itself provides at its location
  (rental chairs, event-run shuttle). It is scoped to its owning event via
  `ScopesByEventOwner` and cascades with the event.

The place system already existed as the `FacilityType` catalog plus
`VenueFacility` value rows attached to `Venue` and `VenueSpace`. This guide
completes that system: a typed availability vocabulary, place-integrity
guards, a seedable default catalog, and documented cleanup. It does not merge
the three systems and does not reuse event attributes for place data.

See [Ownership model](04-usage.md#ownership-model) for the shared-catalog
rules and [Troubleshooting](99-troubleshooting.md#venue-facility-write-rejected)
for guard errors.

## Default catalog and seeding

The package ships five stable catalog codes:

| Code | Name |
|---|---|
| `parking` | Parking |
| `parking_oku` | OKU Parking |
| `aircond` | Air Conditioning |
| `wheelchair_access` | Wheelchair Access |
| `wudu_area` | Wudu Area |

Seed them from an application seeder. The action is idempotent: it inserts
missing codes only, never overwrites app-customized rows, and never touches
unrelated rows. Nothing seeds automatically on boot.

```php
use AIArmada\Events\Actions\SeedFacilityTypesAction;

$result = app(SeedFacilityTypesAction::class)->execute();
// ['created' => 5, 'skipped' => 0] on first run.
```

Seed extra types directly on the model; no application or institution
dependency is required:

```php
use AIArmada\Events\Models\FacilityType;

FacilityType::query()->firstOrCreate(
    ['code' => 'nursing_room'],
    ['name' => 'Nursing Room', 'category' => 'family', 'sort_order' => 60],
);
```

## CRUD

Each snippet below is self-contained: it carries its own imports and runs
place writes in explicit global context, which is the authorized write path
for the shared place catalog from tenant flows. Reads work in any owner
context.

### Venue-wide facility (space null)

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Enums\FacilityAvailability;
use AIArmada\Events\Models\FacilityType;
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Models\VenueFacility;

OwnerContext::withOwner(null, function (): void {
    $venue = Venue::query()->where('slug', 'matrade-hall')->firstOrFail();
    $type = FacilityType::query()->where('code', 'parking')->firstOrFail();

    $facility = VenueFacility::query()->create([
        'venue_id' => $venue->id,
        'venue_space_id' => null,
        'facility_type_id' => $type->id,
        'availability' => FacilityAvailability::Available,
        'quantity' => 400,
        'visibility' => 'public',
    ]);

    $facility->update(['quantity' => 450]);
    $facility->delete();
});
```

### Venue-bound space facility

The venue must own the space; a mismatched pair is rejected.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Enums\FacilityAvailability;
use AIArmada\Events\Models\FacilityType;
use AIArmada\Events\Models\VenueFacility;
use AIArmada\Events\Models\VenueSpace;

OwnerContext::withOwner(null, function (): void {
    $space = VenueSpace::query()->where('slug', 'hall-a')->firstOrFail();
    $type = FacilityType::query()->where('code', 'parking')->firstOrFail();

    $facility = VenueFacility::query()->create([
        'venue_id' => $space->venue_id,
        'venue_space_id' => $space->id,
        'facility_type_id' => $type->id,
        'availability' => FacilityAvailability::Available,
        'visibility' => 'public',
    ]);

    // Creating through the space relation inherits the venue automatically.
    $space->facilities()->create([
        'facility_type_id' => $type->id,
        'visibility' => 'public',
    ]);
});
```

### Standalone template facility (venue null)

A `VenueSpace` with `venue_id = null` is a shared standalone template. Its
facilities carry a null venue id and are visible to every consumer of the
template.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Enums\FacilityAvailability;
use AIArmada\Events\Models\FacilityType;
use AIArmada\Events\Models\VenueFacility;
use AIArmada\Events\Models\VenueSpace;

OwnerContext::withOwner(null, function (): void {
    $template = VenueSpace::query()->where('slug', 'standard-classroom')->firstOrFail();
    $type = FacilityType::query()->where('code', 'parking')->firstOrFail();

    VenueFacility::query()->create([
        'venue_id' => null,
        'venue_space_id' => $template->id,
        'facility_type_id' => $type->id,
        'availability' => FacilityAvailability::Available,
        'visibility' => 'public',
    ]);
});
```

### Place-integrity guards

Every model save (create, update, and relationship writes) enforces:

- a facility type id is present as a valid UUID and exists (null, empty,
  and malformed UUID strings such as `bad-id` are rejected);
- at least one of venue or space is present;
- place ids are valid UUID strings or null: non-string values, empty
  strings, and malformed UUID strings are rejected before any existence
  query, never silently treated as null; valid but unknown UUIDs still run
  the existence check;
- the referenced venue and space rows exist;
- the venue owns the space (a standalone template requires a null venue).

Violations throw `InvalidArgumentException`. Invalid availability values
throw `ValueError` through the native enum cast. Bulk query-builder updates
bypass model events, so keep facility writes on the model.

### Space reparenting

Changing a persisted `VenueSpace::$venue_id` while facilities are attached
is rejected with `InvalidArgumentException`: the attached rows would keep
pointing at the old venue. Remove the space facilities first, then move the
space. Spaces without facilities reparent freely in either direction (venue
to venue, venue to standalone, standalone to venue), and a non-null new
parent must exist.

## Filtering

```php
use AIArmada\Events\Enums\FacilityAvailability;
use AIArmada\Events\Models\FacilityType;
use AIArmada\Events\Models\VenueFacility;

// All values for one venue (venue-wide rows plus its spaces' rows).
$forVenue = VenueFacility::query()->forVenue($venue)->get();

// Rows bound to one space.
$forSpace = VenueFacility::query()->forSpace($space)->get();

// Venue-wide rows only (venue set, space null).
$venueWide = VenueFacility::query()->forVenue($venue)->venueWide()->get();

// By catalog entry or code.
$parking = VenueFacility::query()->forFacilityType($type)->get();
$wudu = VenueFacility::query()->whereTypeCode('wudu_area')->get();

// By typed availability (enum or raw value).
$open = VenueFacility::query()->whereAvailability(FacilityAvailability::Available)->get();

// Active catalog entries in display order.
$catalog = FacilityType::query()->active()->ordered()->get();
```

`forVenue`, `forSpace`, and `forFacilityType` accept a persisted model or
a valid UUID string. Unsaved/idless models (including `new Venue` and
`new VenueSpace`) and malformed UUID strings throw
`InvalidArgumentException` instead of matching `NULL`-place rows; valid
but unknown UUIDs return empty with no existence query. `whereTypeCode`
and `whereAvailability` are unchanged.

Relation scope semantics:

- `Venue::facilities()` returns venue-wide rows plus rows bound to that
  venue's spaces, because both shapes carry the venue id. Standalone
  template facilities are excluded.
- `VenueSpace::facilities()` returns rows bound to that space only. For a
  venue-bound space those rows also appear in `Venue::facilities()`; for a
  standalone template they appear only here.

## Typed availability

`AIArmada\Events\Enums\FacilityAvailability` is a string-backed enum with
`Available` and `Unavailable` cases plus `label()` and `options()` helpers.
`VenueFacility` casts `availability` to it and defaults missing or null
input to `Available`. `EventFacility` keeps its existing string availability;
event semantics are unchanged.

> **warning**
>
> The typed cast is a breaking accessor change: `->availability` on a
> `VenueFacility` now returns `FacilityAvailability`, not a string. String
> consumers must read `->availability->value`. There is no compatibility
> shim.

## Absence semantics

The absence of a `VenueFacility` row means unrecorded/unknown — never read
it as "not offered". Write an explicit `Unavailable` row when a place is
confirmed to lack a facility (for example a hall with no parking), so
consumers can tell "confirmed missing" apart from "nobody recorded it
yet".

## Global catalog writes

`Venue`, `VenueSpace`, `VenueSpaceType`, `FacilityType`, and `VenueFacility`
are intentional global vocabularies shared across owners. They carry no
owner scope, so reads work in any owner context. Catalog writes from tenant
flows must run in explicit global context; the admin surface owns write
authorization for these tables.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\SeedFacilityTypesAction;

OwnerContext::withOwner(null, function (): void {
    app(SeedFacilityTypesAction::class)->execute();
});
```

`SeedFacilityTypesAction` and `SyncVenueFacilitiesAction` already wrap their
writes this way. `SyncVenueFacilitiesAction` reconciles the venue-wide rows
of one venue from catalog codes:

```php
use AIArmada\Events\Actions\SyncVenueFacilitiesAction;
use AIArmada\Events\Enums\FacilityAvailability;
use AIArmada\Events\Models\Venue;

$venue = Venue::query()->where('slug', 'matrade-hall')->firstOrFail();

app(SyncVenueFacilitiesAction::class)->handle($venue, [
    ['code' => 'parking'],
    ['code' => 'wudu_area', 'availability' => FacilityAvailability::Available],
]);
```

The venue must already be persisted; unsaved or deleted venues are rejected
before anything mutates. Unknown codes throw `InvalidArgumentException`,
and venue-wide rows whose codes are missing from the payload are removed —
an empty payload clears the venue-wide rows but leaves space-bound rows and
other venues untouched. Dropped rows delete through the model inside the
sync transaction, so an invalid payload never partially replaces the
existing values.

## Deletion cleanup

Application-level cascades remove facility values without database foreign
keys:

- Deleting a venue removes its venue-wide rows and the rows bound to its
  spaces. Other venues' rows and standalone template rows survive.
- Deleting a space removes the rows bound to that space only.
- Deleting a facility type is blocked when any `EventFacility` row anywhere
  references it. The check runs cross-owner before place values are touched,
  so a rejected delete preserves the catalog row, the place values, and the
  event values. Retire referenced types with `is_active=false` instead; only
  unreferenced types delete, removing just their own `VenueFacility` rows.

Spaces themselves survive venue deletion; only their facility values are
removed.

## Configurable tables

Each model resolves its table from `events.database.tables.*`, so hosts can
prefix or remap tables without touching the package:

```php
// config/events.php
'database' => [
    'tables' => [
        'venues' => env('EVENTS_TABLE_VENUES', 'venues'),
        'venue_spaces' => 'venue_spaces',
        'facility_types' => env('EVENTS_TABLE_FACILITY_TYPES', 'facility_types'),
        'venue_facilities' => env('EVENTS_TABLE_VENUE_FACILITIES', 'venue_facilities'),
    ],
],
```

> **warning**
>
> The `venue_facilities` migration was edited in place to make `venue_id`
> nullable (standalone template support) and to use `foreignUuid` columns.
> Fresh schemas use it on `migrate`. Existing installs must explicitly apply
> the nullable `venue_id` change out of band; no upgrade migration or
> backfill is provided and there is no legacy path.

## Capacity semantics

`VenueSpace::$capacity` is the default or suggested capacity of the shared
space definition or template. Actual per-institution capacity lives
app-side — in ilmu360 on the `institution_space` pivot — and the matching
venue bridge is the app-side `institution_venue` table. No capacity schema
or behavior changed in this package. Applications resolving effective
capacity should total the override with a fallback to the shared default:

```sql
SUM(COALESCE(institution_space.capacity, venue_spaces.capacity))
```

Zero overrides must survive (they are explicit), null pivots fall back to
the shared default, and when both are null the capacity is unknown and
requires app policy — never silently claim zero.

`VenueFacility::$quantity` counts facility units (bays, rooms, counters)
and `VenueFacility::$capacity` counts persons served by that facility
value; both are independent of space capacity. Money stays in integer
minor units (`fee_amount` plus `currency`).

## Rejected: venue operator columns

An operator morph or `institution_id` on `Venue` was rejected.
Institution linkage is an app-side bridge (in ilmu360, the
`institution_venue` table in the host application), not a package concern.
Revisit only if a second application needs the same bridge and can justify
a shared contract.
