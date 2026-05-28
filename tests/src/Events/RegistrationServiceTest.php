<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Actions\CreateRegistrationsForOrderItemAction;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Actions\FulfillEventOrderAction;
use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Data\EventOrderItemFulfillment;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Events\RegistrationCancelled;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Events\RegistrationCreated;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\EventSeries;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Created;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;

it('ensures an occurrence from structured event data', function (): void {
    $occurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => 'Unfair Advantage',
            'slug' => 'unfair-advantage',
            'description' => 'Practical AI events.',
        ],
        event: [
            'name' => 'AI Awakening',
            'slug' => 'ai-awakening',
            'status' => EventStatus::Active,
            'default_timezone' => 'Asia/Kuala_Lumpur',
            'default_duration_minutes' => 240,
            'metadata' => [
                'language_label' => 'Bahasa Melayu',
            ],
        ],
        venue: [
            'name' => 'Menara MATRADE, Kuala Lumpur',
            'slug' => 'menara-matrade-kuala-lumpur',
            'city' => 'Kuala Lumpur',
            'country' => 'MY',
            'timezone' => 'Asia/Kuala_Lumpur',
        ],
        occurrence: [
            'name' => 'AI Awakening — 5 June 2026',
            'capacity' => 120,
            'starts_at' => '2026-06-05 08:45:00',
            'timezone' => 'Asia/Kuala_Lumpur',
            'metadata' => [
                'preferred_date' => '2026-06-05',
            ],
        ],
    );

    OwnerContext::withOwner(null, static fn (): mixed => $occurrence->load(['event.series', 'venue']));

    expect($occurrence->name)->toBe('AI Awakening — 5 June 2026')
        ->and($occurrence->capacity)->toBe(120)
        ->and($occurrence->ends_at)->not->toBeNull()
        ->and($occurrence->event->name)->toBe('AI Awakening')
        ->and($occurrence->event->series->name)->toBe('Unfair Advantage')
        ->and($occurrence->venue->name)->toBe('Menara MATRADE, Kuala Lumpur')
        ->and(data_get($occurrence->metadata, 'preferred_date'))->toBe('2026-06-05');

    $sameOccurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => 'Unfair Advantage',
            'slug' => 'unfair-advantage',
        ],
        event: [
            'name' => 'AI Awakening',
            'slug' => 'ai-awakening',
        ],
        venue: [
            'name' => 'Menara MATRADE, Kuala Lumpur',
            'slug' => 'menara-matrade-kuala-lumpur',
        ],
        occurrence: [
            'starts_at' => '2026-06-05 08:45:00',
            'timezone' => 'Asia/Kuala_Lumpur',
        ],
    );

    expect($sameOccurrence->id)->toBe($occurrence->id)
        ->and(OwnerContext::withOwner(null, static fn (): int => Occurrence::query()->globalOnly()->count()))->toBe(1);
});

it('creates one confirmed registration per purchased seat for an order item', function (): void {
    Event::fake([RegistrationCreated::class]);

    $series = EventSeries::create([
        'name' => 'Unfair Advantage',
        'slug' => 'unfair-advantage',
    ]);

    $event = EventModel::create([
        'event_series_id' => $series->id,
        'name' => 'AI Awakening',
        'slug' => 'ai-awakening',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
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
        'ends_at' => now()->addWeek()->addHours(4),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $purchaser = Customer::create([
        'first_name' => 'Saif',
        'last_name' => 'Fil',
        'email' => 'buyer@example.com',
        'phone' => '+60123456789',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-EVT-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 19400,
        'grand_total' => 19400,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'name' => 'AI Awakening Seat',
        'quantity' => 2,
        'unit_price' => 9700,
        'total' => 19400,
    ]);

    $registrations = app(RegistrationService::class)->createBatchForOrderItem(
        $occurrence,
        $orderItem,
        [
            [
                'name' => 'Saif Fil',
                'email' => 'buyer@example.com',
                'phone' => '+60123456789',
                'company' => 'Buyer Labs Sdn Bhd',
            ],
            [
                'name' => 'Guest Participant',
                'email' => 'guest@example.com',
                'phone' => '+60111222333',
                'company' => 'Guest Ops Sdn Bhd',
            ],
        ],
        $purchaser,
    );

    expect($registrations)->toHaveCount(2)
        ->and(Registration::query()->count())->toBe(2)
        ->and($registrations->every(fn (Registration $registration): bool => $registration->status === RegistrationStatus::Confirmed))->toBeTrue()
        ->and($registrations->pluck('order_item_id')->unique()->all())->toBe([$orderItem->id])
        ->and($registrations->pluck('purchaser_customer_id')->unique()->all())->toBe([$purchaser->id])
        ->and($registrations->pluck('company')->all())->toBe([
            'Buyer Labs Sdn Bhd',
            'Guest Ops Sdn Bhd',
        ]);

    Event::assertDispatched(RegistrationCreated::class, 2);
});

it('creates direct registrations using the occurrence owner even when the ambient owner differs', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Event Owner A',
        'email' => 'event-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Event Owner B',
        'email' => 'event-owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $event = OwnerContext::withOwner($ownerA, fn (): EventModel => EventModel::create([
        'name' => 'Scoped Direct Registration Event',
        'slug' => 'scoped-direct-registration-event',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]));

    $occurrence = OwnerContext::withOwner($ownerA, fn (): Occurrence => Occurrence::create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now()->addWeek(),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]));

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $registration = app(RegistrationService::class)->createForOccurrence($occurrence, [
        'name' => 'Scoped Guest',
        'email' => 'scoped-guest@example.com',
    ]);

    expect($registration->owner_type)->toBe($ownerA->getMorphClass())
        ->and($registration->owner_id)->toBe((string) $ownerA->getKey())
        ->and($registration->occurrence_id)->toBe($occurrence->id);
});

it('idempotently creates registrations for an order item', function (): void {
    $event = EventModel::create([
        'name' => 'Prompting for Teams',
        'slug' => 'prompting-for-teams',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now()->addDays(10),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-EVT-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 19400,
        'grand_total' => 19400,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'name' => 'AI Awakening Seat',
        'quantity' => 2,
        'unit_price' => 9700,
        'total' => 19400,
    ]);

    $participants = [
        [
            'name' => 'First Participant',
            'email' => 'first@example.com',
        ],
        [
            'name' => 'Second Participant',
            'email' => 'second@example.com',
        ],
    ];

    $registrations = app(CreateRegistrationsForOrderItemAction::class)->handle($occurrence, $orderItem, $participants);
    $sameRegistrations = app(CreateRegistrationsForOrderItemAction::class)->handle($occurrence, $orderItem, $participants);

    expect($registrations)->toHaveCount(2)
        ->and($sameRegistrations->pluck('id')->all())->toBe($registrations->pluck('id')->all())
        ->and(Registration::query()->where('order_item_id', $orderItem->id)->count())->toBe(2);
});

it('blocks registrations that would exceed an occurrence capacity', function (): void {
    $event = EventModel::create([
        'name' => 'Capacity Event',
        'slug' => 'capacity-event',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'capacity' => 1,
        'starts_at' => now()->addDays(12),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    Registration::create([
        'occurrence_id' => $occurrence->id,
        'status' => RegistrationStatus::Confirmed,
        'first_name' => 'Existing',
        'last_name' => 'Guest',
        'email' => 'existing@example.com',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-EVT-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 9700,
        'grand_total' => 9700,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'name' => 'AI Awakening Seat',
        'quantity' => 1,
        'unit_price' => 9700,
        'total' => 9700,
    ]);

    expect(fn (): Collection => app(RegistrationService::class)->createBatchForOrderItem(
        $occurrence,
        $orderItem,
        [
            [
                'name' => 'Waiting Guest',
                'email' => 'waiting@example.com',
            ],
        ],
    ))->toThrow(InvalidArgumentException::class, 'sold out');

    expect(Registration::query()->where('occurrence_id', $occurrence->id)->count())->toBe(1);
});

it('blocks direct registrations that would exceed an occurrence capacity', function (): void {
    $event = EventModel::create([
        'name' => 'Direct Capacity Event',
        'slug' => 'direct-capacity-event',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'capacity' => 1,
        'starts_at' => now()->addDays(7),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    Registration::create([
        'occurrence_id' => $occurrence->id,
        'status' => RegistrationStatus::Confirmed,
        'first_name' => 'Booked',
        'last_name' => 'Guest',
        'email' => 'booked@example.com',
    ]);

    expect(fn (): Registration => app(RegistrationService::class)->createForOccurrence($occurrence, [
        'name' => 'Overflow Guest',
        'email' => 'overflow@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'sold out');

    expect(Registration::query()->where('occurrence_id', $occurrence->id)->count())->toBe(1);
});

it('blocks direct registrations when the occurrence is no longer accepting registrations', function (): void {
    $event = EventModel::create([
        'name' => 'Closed Registration Event',
        'slug' => 'closed-registration-event',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now()->addDays(2),
        'registration_closes_at' => now()->subMinute(),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    expect(fn (): Registration => app(RegistrationService::class)->createForOccurrence($occurrence, [
        'name' => 'Late Guest',
        'email' => 'late-guest@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'not accepting registrations');

    expect(Registration::query()->where('occurrence_id', $occurrence->id)->count())->toBe(0);
});

it('requires explicit global owner context for registrations against global occurrences', function (): void {
    [$event, $occurrence] = OwnerContext::withOwner(null, function (): array {
        $event = EventModel::create([
            'name' => 'Global Registration Event',
            'slug' => 'global-registration-event',
            'status' => EventStatus::Active,
            'default_timezone' => 'Asia/Kuala_Lumpur',
        ]);

        $occurrence = Occurrence::create([
            'event_id' => $event->id,
            'status' => OccurrenceStatus::Scheduled,
            'starts_at' => now()->addDay(),
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

        return [$event, $occurrence];
    });

    expect($event->isGlobal())->toBeTrue()
        ->and($occurrence->isGlobal())->toBeTrue();

    expect(fn (): Registration => app(RegistrationService::class)->createForOccurrence($occurrence, [
        'name' => 'Global Guest',
        'email' => 'global-guest@example.com',
    ]))->toThrow(RuntimeException::class, 'Explicit global owner context is required');

    expect(OwnerContext::withOwner(null, fn (): int => Registration::query()->globalOnly()->count()))->toBe(0);
});

it('fulfills event registrations for matching order items through a resolver', function (): void {
    $event = EventModel::create([
        'name' => 'Prompting for Operators',
        'slug' => 'prompting-for-operators',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now()->addDays(12),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-EVT-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 20400,
        'grand_total' => 20400,
    ]);

    $eventOrderItem = OrderItem::create([
        'order_id' => $order->id,
        'name' => 'AI Awakening Seat',
        'sku' => 'event-seat',
        'quantity' => 2,
        'unit_price' => 9700,
        'total' => 19400,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'name' => 'Notebook',
        'sku' => 'notebook',
        'quantity' => 1,
        'unit_price' => 1000,
        'total' => 1000,
    ]);

    app()->bind(EventOrderItemFulfillmentResolver::class, static function () use ($occurrence): EventOrderItemFulfillmentResolver {
        return new class($occurrence) implements EventOrderItemFulfillmentResolver
        {
            public function __construct(private readonly Occurrence $occurrence) {}

            public function resolve(Order $order, OrderItem $orderItem): ?EventOrderItemFulfillment
            {
                if ($orderItem->sku !== 'event-seat') {
                    return null;
                }

                return new EventOrderItemFulfillment(
                    occurrence: $this->occurrence,
                    participants: [
                        [
                            'name' => 'First Participant',
                            'email' => 'first@example.com',
                        ],
                        [
                            'name' => 'Second Participant',
                            'email' => 'second@example.com',
                        ],
                    ],
                );
            }
        };
    });

    $registrations = app(FulfillEventOrderAction::class)->handle($order->fresh(['items']) ?? $order);

    expect($registrations)->toHaveCount(2)
        ->and($registrations->pluck('order_item_id')->unique()->all())->toBe([$eventOrderItem->id])
        ->and(Registration::query()->count())->toBe(2);
});

it('checks in and cancels registrations through the registration service', function (): void {
    Event::fake([RegistrationCheckedIn::class, RegistrationCancelled::class]);

    $event = EventModel::create([
        'name' => 'Prompting for Founders',
        'slug' => 'prompting-for-founders',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now()->addDays(3),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $service = app(RegistrationService::class);

    $registration = $service->createForOccurrence($occurrence, [
        'name' => 'Checked In Guest',
        'email' => 'checkin@example.com',
        'phone' => '+60199888777',
    ], [
        'status' => RegistrationStatus::Confirmed,
    ]);

    $checkedIn = $service->checkIn($registration, ['source' => 'frontdesk']);

    expect($checkedIn->status)->toBe(RegistrationStatus::CheckedIn)
        ->and($checkedIn->checked_in_at)->not->toBeNull()
        ->and(data_get($checkedIn->metadata, 'check_in_context.source'))->toBe('frontdesk');

    $cancellable = $service->createForOccurrence($occurrence, [
        'name' => 'Cancelled Guest',
        'email' => 'cancel@example.com',
    ], [
        'status' => RegistrationStatus::Confirmed,
    ]);

    $cancelled = $service->cancel($cancellable, 'Participant cannot attend');

    expect($cancelled->status)->toBe(RegistrationStatus::Cancelled)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and(data_get($cancelled->metadata, 'cancellation_reason'))->toBe('Participant cannot attend');

    Event::assertDispatched(RegistrationCheckedIn::class);
    Event::assertDispatched(RegistrationCancelled::class);
});

it('blocks check-in when the occurrence is outside its configured check-in window', function (): void {
    $event = EventModel::create([
        'name' => 'Windowed Check-In Event',
        'slug' => 'windowed-check-in-event',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now()->addDay(),
        'check_in_opens_at' => now()->addHour(),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $registration = app(RegistrationService::class)->createForOccurrence($occurrence, [
        'name' => 'Too Early Guest',
        'email' => 'too-early@example.com',
    ], [
        'status' => RegistrationStatus::Confirmed,
    ]);

    expect(fn (): Registration => app(RegistrationService::class)->checkIn($registration, ['source' => 'frontdesk']))
        ->toThrow(InvalidArgumentException::class, 'not currently open for check-in');

    expect($registration->fresh()?->checked_in_at)->toBeNull();
});

it('rolls back batch registrations when one participant payload is invalid', function (): void {
    $event = EventModel::create([
        'name' => 'Batch Safety Event',
        'slug' => 'batch-safety-event',
        'status' => EventStatus::Active,
        'default_timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now()->addDays(5),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-EVT-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 19400,
        'grand_total' => 19400,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'name' => 'AI Awakening Seat',
        'quantity' => 2,
        'unit_price' => 9700,
        'total' => 19400,
    ]);

    expect(fn () => app(RegistrationService::class)->createBatchForOrderItem(
        $occurrence,
        $orderItem,
        [
            [
                'name' => 'Valid Participant',
                'email' => 'valid@example.com',
            ],
            [
                'name' => 'Missing Email',
            ],
        ],
    ))->toThrow(InvalidArgumentException::class);

    expect(Registration::query()->count())->toBe(0);
});
