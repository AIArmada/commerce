---
title: Usage
---

# Usage

## Create or update the event structure

```php
use AIArmada\Events\Actions\EnsureOccurrenceAction;
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
    ],
    venue: [
        'name' => 'MATRADE Hall',
        'slug' => 'matrade-hall',
        'city' => 'Kuala Lumpur',
        'country' => 'MY',
        'timezone' => 'Asia/Kuala_Lumpur',
    ],
    occurrence: [
        'name' => 'AI Awakening — Kuala Lumpur',
        'status' => OccurrenceStatus::Scheduled->value,
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
        'participant_customer_id' => $customer->getKey(),
    ],
);
```

The service rejects new registrations when the occurrence is closed, outside its registration window, or sold out.

## Create registrations from a paid order item

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

## Check in a participant

```php
$checkedIn = app(RegistrationService::class)->checkIn($registration, [
    'source' => 'frontdesk',
]);
```

Check-in only succeeds when the registration is currently `confirmed` and the occurrence is inside its configured check-in window.

## Cancel a registration

```php
$cancelled = app(RegistrationService::class)->cancel(
    $registration,
    'Participant cannot attend',
);
```

Cancellation stores the reason in registration metadata and emits the corresponding domain event.