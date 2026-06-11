---
title: Usage
---

# Usage

## Create or update the event structure

```php
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;

$venue = Venue::query()->create([
    'name' => 'MATRADE Hall',
    'slug' => 'matrade-hall',
    'location_type' => 'hybrid',
    'city' => 'Kuala Lumpur',
    'country' => 'MY',
    'timezone' => 'Asia/Kuala_Lumpur',
]);

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
        'registration_required' => true,
        'media_references' => [
            'cover' => 'https://example.com/ai-awakening-cover.jpg',
        ],
        'taxonomy' => [
            'topic' => ['ai', 'operations'],
            'audience' => ['founders', 'operators'],
        ],
        'people' => [
            [
                'display_name' => 'Aisha Rahman',
                'role' => 'Keynote',
            ],
        ],
    ],
    address: $venue,
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

`EnsureOccurrenceAction` is useful when importing schedules from an external source because it upserts the series, event, and occurrence together and links them to the selected address model.

Set `registration_required` to `true` on events that should accept registrations. The core registration service refuses new registrations when that event-level flag is `false`, and the Filament Events admin surface exposes the same control as an event toggle.

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

If you are building moderation UIs, `EventModerationPolicy::reasonCodeOptions()` returns normalized labels for the configured or default reason codes.

## Search payloads

```php
$payload = $event->load('people')->toSearchableArray();
```

By default the payload includes event identity, public status, moderation status, visibility, media, taxonomy, search keywords, and loaded people names. Bind `AIArmada\Events\Contracts\EventSearchPayloadResolver` or configure `events.search.payload_resolver` for application-specific search engines.

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
use AIArmada\Events\Contracts\RegistrationServiceInterface;

$registration = app(RegistrationServiceInterface::class)->createForOccurrence(
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

The service rejects new registrations when the occurrence is closed, outside its registration window, sold out, or when the parent event has `registration_required` set to `false`.

Use `attendee` when the attendee identity is not necessarily an AIArmada customer. The value can be any Eloquent model and is stored through `attendee_type` / `attendee_id`.

## Record a walk-in

Walk-ins are available for `walk_in_only` and `hybrid` occurrences.

```php
use AIArmada\Events\Contracts\RegistrationServiceInterface;

$walkIn = app(RegistrationServiceInterface::class)->recordWalkInForOccurrence(
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
use AIArmada\Events\Contracts\RegistrationServiceInterface;

$registrations = app(RegistrationServiceInterface::class)->createBatchForOrderItem(
    $occurrence,
    $orderItem,
    $participants,
    $purchaser,
);
```

`createBatchForOrderItem()` expects the participant payload count to match the order-item quantity exactly.

Pending, confirmed, checked-in, and no-show registrations all reserve capacity for the occurrence.

If the commerce packages are not installed, use `createForOccurrence()` for direct event registrations. If the commerce packages are installed and you keep the default resolver, order fulfillment works for order items carrying event checkout metadata. Override the resolver when your application stores that linkage differently.

## Check in a participant

```php
$checkedIn = app(RegistrationServiceInterface::class)->checkIn($registration, [
    'source' => 'frontdesk',
]);
```

Check-in only succeeds when the registration is currently `confirmed` and the occurrence is inside its configured check-in window.

The allowed check-in statuses and occurrence statuses are configurable under `events.lifecycle`.

## Cancel a registration

```php
$cancelled = app(RegistrationServiceInterface::class)->cancel(
    $registration,
    'Participant cannot attend',
);
```

Cancellation stores the reason in registration metadata and emits the corresponding domain event.

## Event membership (host extension)

The package does not own event-team membership, member invitations, or
membership claims. Hosts that need these should:

1. Add a `HasEventMembership` trait to their `Event` model (example below).
2. Emit host-side domain events from inside that trait's methods (e.g.
   `EventOrganizerAdded`).
3. Wire any package listeners they want to react to those events.

The package exposes enough domain events
(`EventModerationTransitioned`, `EventChangeNoticePublished`, `EventPostponed`,
`EventDelayed`, `EventResumed`, `EventCancelled`) for hosts to wire their own
listeners.

### Example trait (host-side, not package code)

```php
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

trait HasEventMembership
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function memberInvitations(): HasMany
    {
        return $this->hasMany(MemberInvitation::class, 'subject_id')
            ->where('subject_type', self::class);
    }

    public function addOrganizer(User $user): void
    {
        $this->members()->syncWithoutDetaching([
            $user->id => ['joined_at' => now()],
        ]);

        Event::dispatch(new EventOrganizerAdded($this, $user, Auth::user()));
    }

    public function inviteMember(string $email, string $role, ?\Carbon\Carbon $expiresAt = null): MemberInvitation
    {
        $invitation = new MemberInvitation([
            'email' => $email,
            'role_slug' => $role,
            'token' => \Illuminate\Support\Str::random(40),
            'expires_at' => $expiresAt,
        ]);

        $this->memberInvitations()->save($invitation);

        return $invitation;
    }

    public function userCanManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('moderator') && $this->is_active) {
            return true;
        }

        return $this->members()->where('users.id', $user->id)->exists();
    }
}
```

## Resolver patterns

The package uses two resolver conventions consistently:

### EventAddressRegistry + EventAddressResolver (registry-and-resolver)

The registry (`EventAddressRegistry`) holds **which** address models are available (reads config, validates they implement `EventAddressable`). The resolver (`EventAddressResolver`) looks up address data and formats it for display. This split keeps configuration separate from presentation:

```php
use AIArmada\Events\Support\Integration\EventAddressRegistry;
use AIArmada\Events\Support\Integration\EventAddressResolver;

// Registry — knows what models are available
$models = EventAddressRegistry::options(); // [Venue::class => 'Venue']

// Resolver — formats address data
$data = app(EventAddressResolver::class)->data($venue);
$label = app(EventAddressResolver::class)->label($venue);
$lines = app(EventAddressResolver::class)->lines($venue);
```

New address models only need to implement `EventAddressable` and be added to `events.addresses.models`. No resolver changes needed.

### Null/Default resolver convention

For optional integrations, the package ships two implementations per contract:

| Pattern | Purpose | When to use |
|---|---|---|
| `Default*` | Built-in behavior | A working implementation ships with the package |
| `Null*` | No-op fallback | The feature may not be installed |

Both implement the same contract. The service provider binds `Default*` when the integration package is detected and `Null*` otherwise. Example contracts with Null variants:

- `EventChangeNoticeNotificationDispatcher` — `NullEventChangeNoticeNotificationDispatcher`
- `EventCheckoutIntentResolver` — `NullEventCheckoutIntentResolver`
- `EventOrderItemFulfillmentResolver` — `NullEventOrderItemFulfillmentResolver`
- `EventReferenceResolver` — `NullEventReferenceResolver`
- `EventScheduleResolver` — `NullEventScheduleResolver`

When adding a new resolver, follow the same pattern: create the contract, a `Default*` implementation, and a `Null*` implementation. The provider binding is the single place that decides which one is active.

The package will never:
- Add `MemberInvitation` / `MembershipClaim` / `event_user` tables.
- Add `allow_public_event_submission` / `public_submission_locked_at` /
  `public_submission_locked_by` columns on the package `Event`.
- Add a `userCanManage()` or `addOrganizer()` method on the package
  `Event` model.
- Emit `EventOrganizerAdded` or `EventMemberInvited` events.

Any PR that adds these will fail the `composer lint:genericity` CI
guardrail (see "Contributing" below).

## Working with the lifecycle state machine

Events have six possible lifecycle states: `Draft`, `Active`, `Postponed`,
`Delayed`, `Cancelled`, `Archived`. Transitions between them are enforced by
the package's `updating` boot hook and rejected with
`AIArmada\Events\Exceptions\InvalidEventStatusTransition` if illegal.

The 11 legal transitions are:

| From | To | Triggered by |
|---|---|---|
| `Draft` | `Active` | publish |
| `Draft` | `Archived` | archive |
| `Active` | `Postponed` | `EventLifecycleWorkflow::postpone()` |
| `Active` | `Delayed` | `EventLifecycleWorkflow::delay()` |
| `Active` | `Cancelled` | `EventLifecycleWorkflow::cancel()` |
| `Active` | `Archived` | archive |
| `Postponed` | `Active` | `EventLifecycleWorkflow::resume()` |
| `Postponed` | `Cancelled` | `EventLifecycleWorkflow::cancel()` |
| `Delayed` | `Active` | `EventLifecycleWorkflow::resume()` |
| `Delayed` | `Postponed` | `EventLifecycleWorkflow::postpone()` (when the delay becomes a postponement) |
| `Delayed` | `Cancelled` | `EventLifecycleWorkflow::cancel()` |

`Cancelled` and `Archived` are terminal. `Postponed` and `Delayed` are
recoverable.

For most use cases, prefer the workflow service over direct status mutation:

```php
use AIArmada\Events\Contracts\EventLifecycleWorkflow;

app(EventLifecycleWorkflow::class)->postpone($event, $moderator, 'Speaker is sick');
app(EventLifecycleWorkflow::class)->delay($event, $moderator, 'Doors not yet open');
app(EventLifecycleWorkflow::class)->resume($event, $moderator, 'New time confirmed');
app(EventLifecycleWorkflow::class)->cancel($event, $moderator, 'Weather emergency', 'storm');
```

Each of these methods:
- Validates the transition against the policy.
- Updates the `status` enum.
- Stamps the `cancelled_at` / `postponed_at` / `delayed_at` timestamp.
- Stamps the `last_state_change_actor_type`, `last_state_change_actor_id`,
  `last_state_change_note`, and `last_state_change_at` columns.
- Dispatches the corresponding `EventPostponed` / `EventDelayed` /
  `EventResumed` / `EventCancelled` domain event after the database
  transaction commits.

## Engagement and registration gating by state

A event is **engageable** (saves, going, interested, registration) only
when its `status` is `Active`. The package enforces this in:

- `RecordEventEngagementAction` — refuses any engagement recording when
  the parent event is not `Active`.
- `RegistrationService::createForOccurrence` — refuses registration
  creation when the parent event is not `Active` (or when the event's
  `registration_required` is `false`).

This is opt-in: a host that wants to allow registration on `Postponed`
events can override the `RegistrationServiceInterface` binding in their adapter.

The `publiclyAccessible()` scope includes `Active`, `Postponed`, `Delayed`,
`Cancelled`, and `Archived` events (i.e., everything except `Draft`). The
`publiclyDiscoverable()` scope is stricter: it returns only `Active` events.

## Schedule modes and resolver hooks

`Occurrence.schedule_mode` is a free-form string column. The default is
`manual` (caller supplies an absolute `starts_at`). For other schedule
modes (`prayer_relative`, `recurring`, `iCal_import`, etc.), the host
binds a class implementing `AIArmada\Events\Contracts\EventScheduleResolver`
via `config(['events.schedule.resolver' => MyResolver::class])`.

When `EnsureOccurrenceAction::handle()` is called and `schedule_mode` is
non-manual but no `starts_at` is supplied, the package calls the
configured resolver to compute the materialized timestamps. If no resolver
is bound, the package throws a `RuntimeException` with a clear message.

```php
use AIArmada\Events\Contracts\EventScheduleResolver;

class PrayerTimeScheduleResolver implements EventScheduleResolver
{
    public function resolve(array $series, array $event, array $location, array $payload): ?array
    {
        $key = $payload['schedule_reference_key'] ?? null;
        $coords = $payload['coordinates'] ?? null;
        $date = $payload['date'] ?? null;

        // hit Aladhan API, map prayer slot, etc.
        // return ['starts_at' => $carbon, 'ends_at' => $carbon->addHour(), 'schedule_label' => 'Fajr + 15 minutes'];
    }
}
```

The package never ships a sample resolver — that would leak religion-
specific vocabulary into a package that must serve any domain with equal
fidelity.

## Slug source field

By default the package derives the slug from the `name` field of the
event. Hosts that prefer to read from a different field (e.g., `title`)
can configure:

```php
// config/events.php
return [
    'slug' => [
        'source_field' => env('EVENTS_SLUG_SOURCE_FIELD', 'name'),
        'max_length' => (int) env('EVENTS_SLUG_MAX_LENGTH', 60),
    ],
    // ...
];
```

The `Event::title` accessor is always available and returns the value of
the `name` field, so hosts can call `$event->title` without renaming the
column.
