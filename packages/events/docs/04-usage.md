---
title: Usage
---

# Usage

## Create the event structure

```php
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSeries;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Venue;

$series = EventSeries::create([
    'name' => 'Unfair Advantage',
    'slug' => 'unfair-advantage',
]);

$event = Event::create([
    'event_series_id' => $series->id,
    'name' => 'AI Awakening',
    'slug' => 'ai-awakening',
    'status' => EventStatus::Active,
]);

$venue = Venue::create([
    'name' => 'MATRADE Hall',
    'slug' => 'matrade-hall',
    'city' => 'Kuala Lumpur',
    'country' => 'MY',
    'timezone' => 'Asia/Kuala_Lumpur',
]);

$occurrence = Occurrence::create([
    'event_id' => $event->id,
    'venue_id' => $venue->id,
    'status' => OccurrenceStatus::Scheduled,
    'starts_at' => now()->addWeek(),
    'timezone' => 'Asia/Kuala_Lumpur',
]);
```

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

## Check in a participant

```php
$checkedIn = app(RegistrationService::class)->checkIn($registration, [
    'source' => 'frontdesk',
]);
```

## Cancel a registration

```php
$cancelled = app(RegistrationService::class)->cancel(
    $registration,
    'Participant cannot attend',
);
```