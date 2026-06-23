---
title: Usage
---

## Creating Events

### Free events

```php
$event = Event::create([
    'title' => 'Free Workshop',
    'pricing_mode' => PricingMode::Free,
    'registration_mode' => RegistrationMode::Required,
    'issue_passes_for_free' => true,
]);
```

Use factory states for convenience:

```php
Event::factory()->free()->published()->create();
Event::factory()->freeWithOptionalRegistration()->published()->create();
Event::factory()->freeOpenDoor()->published()->create();
Event::factory()->mixed()->published()->create();
```

### Inheritance chain

Pricing and registration modes follow this resolution chain:

1. Session override → Occurrence override → Event override → config default / auto-derive
2. Accessors like `effectivePricingMode()` and `effectiveRegistrationMode()` walk the chain and return the resolved value

### Free registration

```php
use AIArmada\Events\Actions\RegisterForFreeAction;

$registrations = app(RegisterForFreeAction::class)->execute(
    target: $occurrence,
    participants: [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'is_primary' => true],
    ],
);
```

The `target` can be an `Event`, `EventOccurrence`, or `EventSession`. Each participant produces one registration.

### Optional registrations (Interested → Confirmed)

For events with `registration_mode = Optional`:

```php
use AIArmada\Events\Actions\RegisterForFreeAction;
use AIArmada\Events\Actions\PromoteInterestedToConfirmedAction;

// Creates an Interested registration (no pass issued)
$registrations = app(RegisterForFreeAction::class)->execute(
    target: $occurrence,
    participants: [['name' => 'Bob']],
    options: ['with_pass' => false],
);

// Promote to confirmed + issue pass later
$confirmed = app(PromoteInterestedToConfirmedAction::class)->execute(
    $registrations->first(),
);
```

### Open-door events (walk-in / headcount)

For events with `registration_mode = None`:

```php
use AIArmada\Events\Actions\RecordWalkInAction;
use AIArmada\Events\Actions\RecordHeadcountLogAction;

// Walk-in: record attendance without a registration
app(RecordWalkInAction::class)->execute(
    target: $occurrence,
    count: 3,
    notes: 'Front desk check-in',
);

// Headcount: just increment a counter
app(RecordHeadcountLogAction::class)->execute(
    target: $occurrence,
    count: 5,
    intervalLabel: '10:00-10:15',
    notes: 'Late arrivals from shuttle bus',
);
```

### Per-level overrides

Sessions and occurrences can override the parent's mode:

```php
$session = $occurrence->sessions()->create([
    'title' => 'Premium Workshop',
    'pricing_mode' => PricingMode::Paid,     // Override free event
    'registration_mode' => RegistrationMode::Required,
    'capacity' => 50,
    ...
]);
```

This allows mixed-mode setups where a free event has a paid add-on session, or vice-versa.
Visibility follows the same event → occurrence → session fallback when a create flow leaves it unset.

### Scopes

```php
// Query scopes
Event::free()->get();              // pricing_mode = Free
Event::mixed()->get();             // pricing_mode = Mixed
Event::openDoor()->get();          // registration_mode = None

// Eager load for N+1-safe accessors
Event::withResolvedModes()->get(); // eager-loads ticketTypes for effectivePricingMode()
```

## Managing Events

```php
use AIArmada\Events\Models\Event;
use AIArmada\Events\States\EventStatus;

$event = Event::create([
    'title' => 'Annual Conference 2026',
    'slug' => 'annual-conference-2026',
    'summary' => 'A one-day tech conference for developers.',
    'type' => 'conference',
    'status' => EventStatus\Draft::class,
    'visibility' => 'public',
    'delivery_mode' => 'physical',
]);
```

### Event lifecycle

Status transitions are handled by `spatie/laravel-model-states`. Use the workflow service to transition with side effects:

```php
use AIArmada\Events\Contracts\EventLifecycleWorkflow;

$workflow = app(EventLifecycleWorkflow::class);

$workflow->publish($event);
$workflow->postpone($event, 'Venue unavailable');
$workflow->delay($occurrence, 'Doors delayed', now()->addHour());
$workflow->cancel($event, 'Weather emergency');
$workflow->archive($event);
$workflow->reschedule($occurrence, $newStart, $newEnd);
$workflow->complete($occurrence);
```

Direct transitions without side effects:

```php
use AIArmada\Events\States\EventStatus;
use AIArmada\Events\States\OccurrenceStatus;
use AIArmada\Events\States\RegistrationStatus;

$event->status->transitionTo(EventStatus\Published::class);
$occurrence->status->transitionTo(OccurrenceStatus\Cancelled::class);
$registration->status->transitionTo(RegistrationStatus\Confirmed::class);
```

Use the workflow service when lifecycle timestamps and domain events are needed. Allowed transitions are defined in each state base class's `config()` method under `States/`.

## Managing Occurrences

```php
$occurrence = $event->occurrences()->create([
    'title' => 'KL Conference Day 1',
    'starts_at' => now()->addDays(30),
    'ends_at' => now()->addDays(30)->addHours(8),
    'timezone' => 'Asia/Kuala_Lumpur',
    'status' => 'scheduled',
    'capacity' => 300,
    'visibility' => 'public',
]);
```

Occurrences represent the actual scheduled run of an event. They carry their own status lifecycle, capacity, and registration windows.

## Managing Sessions

```php
$session = $occurrence->sessions()->create([
    'title' => 'Keynote: Future of AI',
    'slug' => 'keynote-ai',
    'summary' => 'Opening keynote by industry leaders.',
    'starts_at' => $occurrence->starts_at->addHours(1),
    'ends_at' => $occurrence->starts_at->addHours(2),
    'timezone' => 'Asia/Kuala_Lumpur',
    'status' => 'scheduled',
    'capacity' => 300,
    'sort_order' => 1,
]);
```

Sessions are agenda items within an occurrence.

Content inputs are normalized server-side before persistence. Titles are trimmed and repeated whitespace is collapsed, and blank summary or description inputs are stored as `null`.

## Managing Venues

```php
use AIArmada\Events\Models\Venue;

$venue = Venue::create([
    'name' => 'MATRADE Hall',
    'slug' => 'matrade-hall',
    'venue_type' => 'convention_center',
    'line1' => 'Jalan Sultan Haji Ahmad Shah',
    'city' => 'Kuala Lumpur',
    'state' => 'WP Kuala Lumpur',
    'country' => 'MY',
    'timezone' => 'Asia/Kuala_Lumpur',
    'status' => 'active',
    'visibility' => 'public',
]);
```

> [!info]
> Set `events.integrations.addressing_enabled=true` to read venue and event location addresses from the shared addressing package. When the flag is off, the package continues to use the flat address columns.

## Managing Registrations

### Basic registration

```php
use AIArmada\Events\Contracts\RegistrationServiceInterface;

$registration = app(RegistrationServiceInterface::class)->register([
    'event_id' => $event->id,
    'event_occurrence_id' => $occurrence->id,
    'registrant_type' => (new User)->getMorphClass(),
    'registrant_id' => $user->id,
    'registration_type' => 'individual',
    'status' => 'pending',
    'source' => 'website',
    'total_participants' => 2,
    'participants' => [
        [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+60123456789',
            'is_primary' => true,
            'status' => 'registered',
        ],
        [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'relationship_to_registrant' => 'spouse',
            'is_primary' => false,
            'status' => 'registered',
        ],
    ],
    'items' => [
        [
            'event_ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'total_price' => 100000,
        ],
    ],
]);
```

### Registration with occurrence/session-scoped participants

```php
$registration = EventRegistration::create([
    'event_id' => $event->id,
    'event_occurrence_id' => $occurrence->id,
    'event_session_id' => $session->id,
    'registration_type' => 'individual',
    'status' => 'confirmed',
    'source' => 'admin',
]);

// Participants can be scoped directly to an occurrence or session
$participant = $registration->participants()->create([
    'event_occurrence_id' => $occurrence->id,
    'event_session_id' => $session->id,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'is_primary' => true,
    'status' => 'registered',
]);

// Direct queries on occurrences and sessions
$session->participants;      // All participants for this session
$occurrence->participants;   // All participants for this occurrence
$participant->occurrence;    // BelongsTo relationship
$participant->session;       // BelongsTo relationship
```

### Registration lifecycle

```php
$service = app(RegistrationServiceInterface::class);

$service->approve($registration);           // pending → confirmed
$service->cancel($registration, 'No-show'); // any → cancelled
$service->reject($registration, 'Duplicate');
$service->waitlist($registration);
$service->complete($registration);
```

### Order-based registration

```php
$service->createFromOrderItem([
    'event_id' => $event->id,
    'event_occurrence_id' => $occurrence->id,
    'quantity' => 2,
    'total_price' => 100000,
    'currency' => 'MYR',
    'order_id' => $order->id,
    'registrant_type' => (new Customer)->getMorphClass(),
    'registrant_id' => $customer->id,
]);
```

## Managing Ticket Types

```php
use AIArmada\Events\Models\EventTicketType;

$ticketType = EventTicketType::create([
    'event_id' => $event->id,
    'event_occurrence_id' => $occurrence->id,
    'name' => 'Early Bird',
    'code' => 'EARLY',
    'access_type' => 'entry',
    'price' => 50000,
    'currency' => 'MYR',
    'quota' => 100,
    'status' => 'active',
    'visibility' => 'public',
    'sales_starts_at' => now()->subMonth(),
    'sales_ends_at' => $occurrence->starts_at,
]);
```

## Check-in and Attendance

```php
use AIArmada\Events\Contracts\EventCheckInService;

app(EventCheckInService::class)->checkIn([
    'event_id' => $event->id,
    'event_occurrence_id' => $occurrence->id,
    'event_registration_id' => $registration->id,
    'event_registration_participant_id' => $participant->id,
    'event_pass_id' => $pass->id,
    'attendance_type' => 'registered',
    'check_in_source' => 'qr',
]);
```

## Managing Involvements

```php
$event->involvements()->create([
    'involveable_type' => (new Speaker)->getMorphClass(),
    'involveable_id' => $speaker->id,
    'role_code' => 'speaker',
    'prominence' => 'headliner',
    'is_featured' => true,
    'status' => 'confirmed',
]);
```

## Owner-Scoped Queries

When `events.owner.enabled` is `true`, all models use the `HasOwner` trait with automatic scoping:

```php
// Automatically scoped to current owner
$myEvents = Event::all();

// Explicit owner context
use AIArmada\CommerceSupport\Support\OwnerContext;

OwnerContext::withOwner($masjid, function () use ($registrant) {
    Event::create([...]);
});

OwnerContext::withOwner(null, function () use ($registrant) {
    // Explicit global context for global records
    Event::globalOnly()->get();
});

// Without owner scope
Event::withoutOwnerScope()->get();
```

## Working with Passes

```php
use AIArmada\Events\Models\EventPass;

$pass = EventPass::create([
    'event_id' => $event->id,
    'event_occurrence_id' => $occurrence->id,
    'event_registration_id' => $registration->id,
    'event_registration_participant_id' => $participant->id,
    'event_ticket_type_id' => $ticketType->id,
    'pass_no' => 'PASS-00001',
    'qr_code' => Str::random(32),
    'status' => 'issued',
]);
```

## Stale slug redirects

When a model's slug changes, the old slug automatically issues a 308 redirect to the new URL via spatie/laravel-sluggable's self-healing URLs.

## Selling Tickets via Commerce Checkout

When `aiarmada/cart`, `aiarmada/checkout`, and `aiarmada/orders` are installed, ticket types can be sold through the standard commerce checkout pipeline alongside products.

### Adding ticket types to cart

```php
use AIArmada\Events\Actions\AddEventTicketTypeToCartAction;
use AIArmada\Events\Models\EventTicketType;

$ticketType = EventTicketType::find('...');

AddEventTicketTypeToCartAction::make()->handle(
    cart: cart(),
    ticketType: $ticketType,
    quantity: 2,
    participants: [
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com'],
    ],
);
```

The action validates status, sales windows, min/max quantity, and remaining quota before adding. It handles cart merging — if the same ticket type is already in the cart, quantities and participants are merged rather than overwritten. Session-scoped ticket types preserve `event_session_id` in the cart item attributes.

### Mixed carts (tickets + products)

Ticket types and products can coexist in the same cart. The checkout pipeline runs pricing, discounts, shipping, tax, and payment uniformly. After order creation, `CreateEventRegistrationsStep` (auto-registered) processes event ticket items by resolving the matching event, occurrence, or session scope from each `EventTicketType`, then creates registrations and passes. Product items flow through normal order fulfillment.

### Participant data

Participant data is stored in the cart item's `attributes.participants` array. The step resolves it in priority order:
1. Participants from the cart item attributes (passed via `AddEventTicketTypeToCartAction`)
2. Fallback to the order customer's name/email/phone
3. Generic "Attendee #N" entries

One participant entry produces one registration with one ticket item — matching the order item's quantity.

### Quota validation

Quota is checked by counting `EventRegistrationItem` quantity across capacity-blocking statuses (`pending`, `confirmed`, `checked_in`, `no_show`). Quota is not checked during checkout intent (re-entering checkout for an existing registration). The inventory package is not required; ticket capacity is self-contained.

### Checkout intent resolver

`DefaultEventCheckoutIntentResolver` is bound when both cart and checkout packages are available. It creates a dedicated cart instance for the registration, preserving participant data:

```php
use AIArmada\Events\Actions\StartOccurrenceCheckoutAction;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;

$target = EventOccurrence::find('...');

StartOccurrenceCheckoutAction::make()->handle($target, $registration);
// Returns CheckoutSession from the commerce pipeline
```

The first argument can be either an occurrence or a session.

Override via config `events.integrations.checkout_intent_resolver` or by binding `EventCheckoutIntentResolver`.

### Per-scope ticket types

When a ticket type's `event_occurrence_id` or `event_session_id` is set, it is scoped to that specific occurrence or session. Both `AddEventTicketTypeToCartAction` (via attributes) and `CreateRegistrationsFromOrderAction` (via validation) enforce scope matching. Ticket types without an occurrence or session are event-wide and can be registered for any matching scope.

## Extensibility via Contracts

The package exposes contracts for every major operation. Bind your own implementation to override default behavior:

```php
use AIArmada\Events\Contracts\EventSearchPayloadResolver;

app()->bind(EventSearchPayloadResolver::class, MySearchResolver::class);
```

Available contracts: `RegistrationServiceInterface`, `EventLifecycleWorkflow`, `EventCheckInService`, `EventSearchPayloadResolver`, `EventDisplayTimezoneResolver`, `EventScheduleResolver`, and more.

## Search Indexing

When `events.sync.build_search_documents` is enabled, the package maintains `event_search_documents` automatically through the built-in search indexer for events, occurrences, and sessions.

```php
use AIArmada\Events\Services\EventSearchDocumentBuilder;

$builder = app(EventSearchDocumentBuilder::class);
$document = $builder->buildForEvent($event);
$occurrenceDocument = $builder->buildForOccurrence($occurrence);
$sessionDocument = $builder->buildForSession($session);
```

The generated payload includes the target content, the current `metadata` snapshot for that target, and relation-backed facets for audiences and classifications when those sync flags are enabled.

Set `events.search.queue_indexing=true` to queue rebuilds instead of writing them synchronously. If you need to disable automatic indexing entirely, bind `events.search.indexer` to `AIArmada\Events\Resolvers\NullEventSearchIndexer`.
