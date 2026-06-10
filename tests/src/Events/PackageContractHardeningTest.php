<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Actions\FulfillEventOrderAction;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Contracts\EventChangeNoticeWorkflow;
use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\EventSeries;
use AIArmada\Events\Models\EventSubLocation;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Resolvers\DefaultEventOrderItemFulfillmentResolver;
use AIArmada\Events\Resolvers\NullEventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Services\DefaultEventChangeNoticeWorkflow;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Created;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('uses collision-resistant package table defaults', function (): void {
    expect(config('events.database.tables'))->toMatchArray([
        'series' => 'event_series',
        'events' => 'events',
        'people' => 'event_speakers',
        'venues' => 'event_venues',
        'sub_locations' => 'event_sub_locations',
        'occurrences' => 'event_occurrences',
        'registrations' => 'event_registrations',
        'classifications' => 'event_classifications',
        'assets' => 'event_assets',
        'references' => 'event_reference_assignments',
        'submissions' => 'event_submissions',
        'reviews' => 'event_reviews',
        'change_notices' => 'event_change_notices',
        'agenda_items' => 'event_agenda_items',
        'attendance' => 'event_attendance',
        'engagements' => 'event_engagements',
    ])
        ->and((new EventSeries)->getTable())->toBe('event_series')
        ->and((new EventModel)->getTable())->toBe('events')
        ->and((new Venue)->getTable())->toBe('event_venues')
        ->and((new EventSubLocation)->getTable())->toBe('event_sub_locations')
        ->and((new Occurrence)->getTable())->toBe('event_occurrences')
        ->and((new Registration)->getTable())->toBe('event_registrations')
        ->and(config('events.models.sub_location'))->toBe(EventSubLocation::class)
        ->and(Schema::hasTable('events'))->toBeTrue()
        ->and(Schema::hasTable('event_series'))->toBeTrue()
        ->and(Schema::hasTable('event_speakers'))->toBeTrue()
        ->and(Schema::hasTable('event_sub_locations'))->toBeTrue()
        ->and(Schema::hasTable('event_classifications'))->toBeTrue()
        ->and(Schema::hasTable('event_assets'))->toBeTrue()
        ->and(Schema::hasTable('event_reference_assignments'))->toBeTrue()
        ->and(Schema::hasTable('event_submissions'))->toBeTrue()
        ->and(Schema::hasTable('event_reviews'))->toBeTrue()
        ->and(Schema::hasTable('event_change_notices'))->toBeTrue()
        ->and(Schema::hasTable('event_agenda_items'))->toBeTrue()
        ->and(Schema::hasTable('event_attendance'))->toBeTrue()
        ->and(Schema::hasTable('event_engagements'))->toBeTrue()
        ->and(config('events.change_notices.notification_dispatcher'))->toBeNull()
        ->and(app(EventChangeNoticeWorkflow::class))->toBeInstanceOf(DefaultEventChangeNoticeWorkflow::class)
        ->and(app(EventChangeNoticeNotificationDispatcher::class))->toBeInstanceOf(NullEventChangeNoticeNotificationDispatcher::class);
});

it('resolves host event and polymorphic address models for occurrence relationships', function (): void {
    config()->set('events.models.event', User::class);

    $hostEvent = User::query()->create([
        'name' => 'Host Event Record',
        'email' => 'host-event@example.com',
        'password' => 'secret',
    ]);

    $hostAddress = Venue::query()->create([
        'name' => 'Host Venue Record',
        'slug' => 'host-venue-record',
        'city' => 'Kuala Lumpur',
        'country' => 'MY',
        'timezone' => 'UTC',
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $hostEvent->id,
        'address_type' => $hostAddress->getMorphClass(),
        'address_id' => $hostAddress->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now('UTC')->addDay(),
        'timezone' => 'UTC',
    ]);

    $occurrence->load(['event', 'address']);

    expect($occurrence->event)->toBeInstanceOf(User::class)
        ->and($occurrence->event?->getKey())->toBe($hostEvent->getKey())
        ->and($occurrence->address)->toBeInstanceOf(Venue::class)
        ->and($occurrence->address?->getKey())->toBe($hostAddress->getKey());
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

        'registration_required' => true,
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

        'registration_required' => true,
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

it('no-ops order fulfillment when no event checkout metadata is present', function (): void {
    $tableName = (new OrderItem)->getTable();
    if (! Schema::hasColumn($tableName, 'status')) {
        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('status', 30)->default('active');
        });
    }

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

    expect(app(EventOrderItemFulfillmentResolver::class))->toBeInstanceOf(DefaultEventOrderItemFulfillmentResolver::class)
        ->and($registrations)->toHaveCount(0)
        ->and(Registration::query()->count())->toBe(0);
});
