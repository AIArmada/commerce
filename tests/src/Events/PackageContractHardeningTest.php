<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Actions\FulfillEventOrderAction;
use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\EventSeries;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Resolvers\NullEventOrderItemFulfillmentResolver;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Created;
use Illuminate\Support\Facades\Schema;

it('uses collision-resistant package table defaults', function (): void {
    expect(config('events.database.tables'))->toMatchArray([
        'series' => 'event_series',
        'events' => 'events',
        'speakers' => 'event_speakers',
        'venues' => 'event_venues',
        'occurrences' => 'event_occurrences',
        'registrations' => 'event_registrations',
    ])
        ->and((new EventSeries)->getTable())->toBe('event_series')
        ->and((new EventModel)->getTable())->toBe('events')
        ->and((new Venue)->getTable())->toBe('event_venues')
        ->and((new Occurrence)->getTable())->toBe('event_occurrences')
        ->and((new Registration)->getTable())->toBe('event_registrations')
        ->and(Schema::hasTable('events'))->toBeTrue()
        ->and(Schema::hasTable('event_series'))->toBeTrue()
        ->and(Schema::hasTable('event_speakers'))->toBeTrue();
});

it('resolves host event and venue models from config for occurrence relationships', function (): void {
    config()->set('events.models.event', User::class);
    config()->set('events.models.venue', User::class);

    $hostEvent = User::query()->create([
        'name' => 'Host Event Record',
        'email' => 'host-event@example.com',
        'password' => 'secret',
    ]);

    $hostVenue = User::query()->create([
        'name' => 'Host Venue Record',
        'email' => 'host-venue@example.com',
        'password' => 'secret',
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $hostEvent->id,
        'venue_id' => $hostVenue->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now('UTC')->addDay(),
        'timezone' => 'UTC',
    ]);

    $occurrence->load(['event', 'venue']);

    expect($occurrence->event)->toBeInstanceOf(User::class)
        ->and($occurrence->event?->getKey())->toBe($hostEvent->getKey())
        ->and($occurrence->venue)->toBeInstanceOf(User::class)
        ->and($occurrence->venue?->getKey())->toBe($hostVenue->getKey());
});

it('stores generic attendee identity for direct registrations', function (): void {
    $attendee = User::query()->create([
        'name' => 'Direct Attendee',
        'email' => 'direct-attendee@example.com',
        'password' => 'secret',
    ]);

    $event = EventModel::query()->create([
        'name' => 'Attendee Adapter Event',
        'slug' => 'attendee-adapter-event',
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now('UTC')->addDay(),
        'timezone' => 'UTC',
    ]);

    $registration = app(RegistrationService::class)->createForOccurrence(
        $occurrence,
        [
            'name' => 'Direct Attendee',
            'email' => 'direct-attendee@example.com',
        ],
        [
            'attendee' => $attendee,
        ],
    );

    $registration->load('attendee');

    expect($registration->attendee_type)->toBe($attendee->getMorphClass())
        ->and($registration->attendee_id)->toBe((string) $attendee->getKey())
        ->and($registration->attendee)->toBeInstanceOf(User::class)
        ->and($registration->attendee?->getKey())->toBe($attendee->getKey());
});

it('lets config redefine lifecycle status rules', function (): void {
    config()->set('events.lifecycle.occurrence.registration_accepting_statuses', ['draft']);
    config()->set('events.lifecycle.registration.capacity_blocking_statuses', [RegistrationStatus::Confirmed->value]);

    $event = EventModel::query()->create([
        'name' => 'Configurable Lifecycle Event',
        'slug' => 'configurable-lifecycle-event',
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Draft,
        'capacity' => 1,
        'starts_at' => now('UTC')->addDay(),
        'timezone' => 'UTC',
    ]);

    Registration::query()->create([
        'occurrence_id' => $occurrence->id,
        'status' => RegistrationStatus::Pending,
        'first_name' => 'Pending',
        'last_name' => 'Guest',
        'email' => 'pending@example.com',
    ]);

    $registration = app(RegistrationService::class)->createForOccurrence($occurrence, [
        'name' => 'Second Pending Guest',
        'email' => 'second-pending@example.com',
    ]);

    expect($occurrence->fresh()?->acceptsRegistrations())->toBeTrue()
        ->and($registration->status)->toBe(RegistrationStatus::Pending)
        ->and(Registration::query()->where('occurrence_id', $occurrence->id)->count())->toBe(2);
});

it('normalizes action-written occurrence timestamps to UTC while preserving the source timezone label', function (): void {
    $occurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => 'UTC Safety',
            'slug' => 'utc-safety',
        ],
        event: [
            'name' => 'UTC Safety Event',
            'slug' => 'utc-safety-event',
            'default_duration_minutes' => 90,
            'default_timezone' => 'Asia/Kuala_Lumpur',
        ],
        venue: null,
        occurrence: [
            'starts_at' => '2026-06-05 08:45:00',
            'registration_opens_at' => '2026-06-01 09:00:00',
            'timezone' => 'Asia/Kuala_Lumpur',
        ],
    );

    expect($occurrence->timezone)->toBe('Asia/Kuala_Lumpur')
        ->and($occurrence->starts_at->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'))->toBe('2026-06-05 00:45:00')
        ->and($occurrence->ends_at?->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'))->toBe('2026-06-05 02:15:00')
        ->and($occurrence->registration_opens_at?->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'))->toBe('2026-06-01 01:00:00');
});

it('no-ops order fulfillment when no resolver is configured', function (): void {
    $order = Order::query()->create([
        'order_number' => 'ORD-EVT-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 9700,
        'grand_total' => 9700,
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'name' => 'Unresolved Event Seat',
        'sku' => 'unresolved-event-seat',
        'quantity' => 1,
        'unit_price' => 9700,
        'total' => 9700,
    ]);

    $registrations = app(FulfillEventOrderAction::class)->handle($order->fresh(['items']) ?? $order);

    expect(app(EventOrderItemFulfillmentResolver::class))->toBeInstanceOf(NullEventOrderItemFulfillmentResolver::class)
        ->and($registrations)->toHaveCount(0)
        ->and(Registration::query()->count())->toBe(0);
});
