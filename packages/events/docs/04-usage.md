---
title: Usage
---

## Creating Events

```php
use AIArmada\Events\Models\Event;

$event = Event::create([
    'title' => 'Annual Conference 2026',
    'slug' => 'annual-conference-2026',
    'summary' => 'A one-day tech conference for developers.',
    'type' => 'conference',
    'status' => Event::DRAFT,
    'visibility' => 'public',
    'delivery_mode' => 'physical',
]);
```

### Event lifecycle

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

Direct status mutation is allowed, but using the workflow service ensures lifecycle timestamps are stamped and domain events are dispatched.

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

## Managing Venues

```php
use AIArmada\Events\Models\Venue;

$venue = Venue::create([
    'name' => 'MATRADE Hall',
    'slug' => 'matrade-hall',
    'venue_type' => 'convention_center',
    'address_line_1' => 'Jalan Sultan Haji Ahmad Shah',
    'city' => 'Kuala Lumpur',
    'state' => 'WP Kuala Lumpur',
    'country' => 'MY',
    'timezone' => 'Asia/Kuala_Lumpur',
    'status' => 'active',
    'visibility' => 'public',
]);
```

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

The action validates status, sales windows, min/max quantity, and remaining quota before adding. It handles cart merging — if the same ticket type is already in the cart, quantities and participants are merged rather than overwritten.

### Mixed carts (tickets + products)

Ticket types and products can coexist in the same cart. The checkout pipeline runs pricing, discounts, shipping, tax, and payment uniformly. After order creation, `CreateEventRegistrationsStep` (auto-registered) selectively processes only order items where `purchasable instanceof EventTicketType`, creating registrations and passes. Product items flow through normal order fulfillment.

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

StartOccurrenceCheckoutAction::make()->handle($occurrence, $registration);
// Returns CheckoutSession from the commerce pipeline
```

Override via config `events.integrations.checkout_intent_resolver` or by binding `EventCheckoutIntentResolver`.

### Per-occurrence ticket types

When a ticket type's `event_occurrence_id` is set, it is scoped to that specific occurrence. Both `AddEventTicketTypeToCartAction` (via attributes) and `CreateRegistrationsForOrderItemAction` (via validation) enforce occurrence-scoping. Ticket types without an occurrence are event-wide and can be registered for any occurrence of the event.

## Extensibility via Contracts

The package exposes contracts for every major operation. Bind your own implementation to override default behavior:

```php
use AIArmada\Events\Contracts\EventSearchPayloadResolver;

app()->bind(EventSearchPayloadResolver::class, MySearchResolver::class);
```

Available contracts: `RegistrationServiceInterface`, `EventLifecycleWorkflow`, `EventCheckInService`, `EventSearchPayloadResolver`, `EventDisplayTimezoneResolver`, `EventScheduleResolver`, and more.
