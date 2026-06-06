---
title: Usage
---

# Usage

## Create or update the event structure

```php
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;

$occurrence = app(EnsureOccurrenceAction::class)->handle(
    series: [
        'name' => 'Unfair Advantage',
        'slug' => 'unfair-advantage',
    ],
    event: [
        'name' => 'AI Awakening',
        'slug' => 'ai-awakening',
        'summary' => 'A one-day AI intensive for founders and operators.',
        'status' => EventStatus::Active,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
        'default_timezone' => 'Asia/Kuala_Lumpur',
        'media_references' => [
            'cover' => 'https://example.com/ai-awakening-cover.jpg',
        ],
        'taxonomy' => [
            'topic' => ['ai', 'operations'],
            'audience' => ['founders', 'operators'],
        ],
        'speakers' => [
            [
                'display_name' => 'Aisha Rahman',
                'role' => 'Keynote',
            ],
        ],
    ],
    venue: [
        'name' => 'MATRADE Hall',
        'slug' => 'matrade-hall',
        'location_type' => 'hybrid',
        'city' => 'Kuala Lumpur',
        'country' => 'MY',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'timezone' => 'Asia/Kuala_Lumpur',
    ],
    occurrence: [
        'name' => 'AI Awakening — Kuala Lumpur',
        'status' => OccurrenceStatus::Scheduled->value,
        'participation_mode' => OccurrenceParticipationMode::Hybrid->value,
        'starts_at' => '2026-08-21 10:00:00',
        'capacity' => 300,
        'timezone' => 'Asia/Kuala_Lumpur',
        'registration_opens_at' => '2026-07-01 00:00:00',
        'registration_closes_at' => '2026-08-20 23:59:59',
        'check_in_opens_at' => '2026-08-21 08:30:00',
        'check_in_closes_at' => '2026-08-21 11:00:00',
    ],
    owner: $store,
);
```

`EnsureOccurrenceAction` is useful when importing schedules from an external source because it upserts the series, event, venue, and occurrence together.

## Public event visibility

Package-owned events separate operational status from review and public visibility:

```php
use AIArmada\Events\Models\Event;

$discoverableEvents = Event::query()
    ->publiclyDiscoverable()
    ->get();

$accessibleByDirectLink = Event::query()
    ->publiclyAccessible()
    ->get();
```

- `publiclyAccessible()` includes approved active public and unlisted events inside their publication windows.
- `publiclyDiscoverable()` includes only approved active public events inside their publication windows.
- `searchable()` currently follows `publiclyDiscoverable()` and can be paired with the package search payload resolver.

## Search payloads

```php
$payload = $event->load('speakers')->toSearchableArray();
```

By default the payload includes event identity, public status, moderation status, visibility, media, taxonomy, search keywords, and loaded speaker names. Bind `AIArmada\Events\Contracts\EventSearchPayloadResolver` or configure `events.search.payload_resolver` for application-specific search engines.

## Display timezone

```php
$timezone = $occurrence->displayTimezone($viewer);
$startsAt = $occurrence->startsAtForDisplay($viewer);
```

The default resolver prefers a viewer `timezone` attribute, then occurrence timezone, then event default timezone, then `events.timezone.default`. Applications can replace this by binding `AIArmada\Events\Contracts\EventDisplayTimezoneResolver`.

## Choose the occurrence participation mode

Occurrences default to `registration_required`.

Use `participation_mode` when the occurrence should be non-registration, walk-in only, or hybrid:

```php
use AIArmada\Events\Enums\OccurrenceParticipationMode;

$occurrence->update([
    'participation_mode' => OccurrenceParticipationMode::WalkInOnly,
]);
```

Modes:

- `none`: no package-managed registration or walk-in attendance
- `registration_required`: pre-registration is accepted; walk-ins are rejected
- `walk_in_only`: pre-registration is rejected; walk-ins can be recorded
- `hybrid`: both pre-registration and walk-ins are accepted

If the host application owns a richer public event model, configure `events.models.event` to that model and create `Occurrence` rows against the host event ID directly. `EnsureOccurrenceAction` remains for package-owned event definitions.

```php
use AIArmada\Events\Models\Occurrence;

config(['events.models.event' => App\Models\Event::class]);

$occurrence = Occurrence::query()->create([
    'event_id' => $publicEvent->getKey(),
    'status' => 'scheduled',
    'starts_at' => $startsAtUtc,
    'timezone' => 'Asia/Kuala_Lumpur',
]);

$publicEvent = $occurrence->event;
```

## Create a single registration

```php
use AIArmada\Events\Services\RegistrationService;

$registration = app(RegistrationService::class)->createForOccurrence(
    $occurrence,
    [
        'name' => 'Saif Fil',
        'email' => 'saif@example.com',
        'phone' => '+60123456789',
    ],
    [
        'attendee' => $user,
        'participant_customer_id' => $customer->getKey(),
    ],
);
```

The service rejects new registrations when the occurrence is closed, outside its registration window, or sold out.

Use `attendee` when the attendee identity is not necessarily an AIArmada customer. The value can be any Eloquent model and is stored through `attendee_type` / `attendee_id`.

## Record a walk-in

Walk-ins are available for `walk_in_only` and `hybrid` occurrences.

```php
use AIArmada\Events\Services\RegistrationService;

$walkIn = app(RegistrationService::class)->recordWalkInForOccurrence(
    $occurrence,
    [
        'name' => 'Walk In Guest',
        'phone' => '+60123456789',
    ],
);
```

Walk-ins are stored as checked-in registration rows with `attendance_source = walk_in`. Email is optional. If no name is provided, the package stores `Walk-in Attendee`.

Walk-ins share the occurrence `capacity` with pre-registered attendees.

## Create registrations from a paid order item

This flow is available when `aiarmada/orders` and `aiarmada/customers` are installed with `aiarmada/events`.

Configure an order-item fulfillment resolver before relying on automatic order fulfillment:

```php
// config/events.php
'integrations' => [
    'order_item_fulfillment_resolver' => App\Events\EventSeatFulfillmentResolver::class,
],
```

```php
use AIArmada\Events\Services\RegistrationService;

$registrations = app(RegistrationService::class)->createBatchForOrderItem(
    $occurrence,
    $orderItem,
    $participants,
    $purchaser,
);
```

`createBatchForOrderItem()` expects the participant payload count to match the order-item quantity exactly.

Pending, confirmed, checked-in, and no-show registrations all reserve capacity for the occurrence.

If the commerce packages are not installed, use `createForOccurrence()` for direct event registrations. If the commerce packages are installed but no resolver is configured, order fulfillment returns an empty collection.

## Check in a participant

```php
$checkedIn = app(RegistrationService::class)->checkIn($registration, [
    'source' => 'frontdesk',
]);
```

Check-in only succeeds when the registration is currently `confirmed` and the occurrence is inside its configured check-in window.

The allowed check-in statuses and occurrence statuses are configurable under `events.lifecycle`.

## Cancel a registration

```php
$cancelled = app(RegistrationService::class)->cancel(
    $registration,
    'Participant cannot attend',
);
```

Cancellation stores the reason in registration metadata and emits the corresponding domain event.
