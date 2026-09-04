<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\CreateRegistrationsFromOrderAction;
use AIArmada\Events\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', true);
});

it('creates registrations for an order item using the order model as the external order type', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $originalMorphMap = Relation::morphMap();
        Relation::morphMap(['test-order' => Order::class], false);

        try {
            $event = Event::factory()->paid()->create();
            $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
            $ticketType = createEventTicketType($event, ['price' => 1500]);
            $order = Order::factory()->create();
            $orderItem = OrderItem::query()->create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'purchasable_type' => $ticketType->getMorphClass(),
                'purchasable_id' => $ticketType->id,
                'name' => 'Event Ticket',
                'sku' => 'EVENT-TICKET',
                'quantity' => 2,
                'unit_price' => 1500,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'currency' => 'MYR',
            ]);

            $registrations = app(CreateRegistrationsFromOrderAction::class)->handle(
                target: $occurrence,
                orderItem: $orderItem->fresh(),
                participants: [
                    ['name' => 'Alice Example', 'email' => 'alice@example.com'],
                    ['name' => 'Bob Example', 'email' => 'bob@example.com'],
                ],
            );

            expect($registrations)->toHaveCount(2);

            $persistedRegistrations = EventRegistration::query()
                ->where('event_occurrence_id', $occurrence->id)
                ->get();

            expect($persistedRegistrations)->toHaveCount(2)
                ->and($persistedRegistrations->pluck('external_order_id')->unique()->all())->toBe([$order->id])
                ->and($persistedRegistrations->pluck('external_order_type')->unique()->all())->toBe([Order::class]);

            $firstRegistration = $persistedRegistrations->first();
            expect($firstRegistration?->items)->toHaveCount(1)
                ->and($firstRegistration?->items->first()?->external_order_item_id)->toBe($orderItem->id)
                ->and($firstRegistration?->items->first()?->external_order_item_type)->toBe(OrderItem::class);

            expect(EventRegistration::query()->byOrder($order)->count())->toBe(2);
        } finally {
            Relation::morphMap($originalMorphMap, false);
        }
    });
});

it('can create a pending order registration for offline payment confirmation', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $originalMorphMap = Relation::morphMap();
        Relation::morphMap(['test-order' => Order::class], false);

        try {
            $event = Event::factory()->paid()->create();
            $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
            $ticketType = createEventTicketType($occurrence, ['price' => 1500]);
            $order = Order::factory()->create([
                'grand_total' => 1500,
                'currency' => 'MYR',
            ]);
            $orderItem = OrderItem::query()->create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'purchasable_type' => $ticketType->getMorphClass(),
                'purchasable_id' => $ticketType->id,
                'name' => 'Offline Event Ticket',
                'sku' => 'OFFLINE-EVENT-TICKET',
                'quantity' => 1,
                'unit_price' => 1500,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => 1500,
                'currency' => 'MYR',
            ]);

            $registrations = app(CreateRegistrationsFromOrderAction::class)->handle(
                target: $occurrence,
                orderItem: $orderItem->fresh(),
                participants: [['name' => 'Offline Attendee']],
                options: [
                    'registration_status' => 'pending',
                    'item_status' => 'pending',
                    'source' => 'offline_admission',
                    'payment_status' => 'pending',
                    'metadata' => ['offline_admission' => true],
                ],
            );

            $registration = $registrations->first();

            expect($registration)->not->toBeNull()
                ->and($registration?->status->getValue())->toBe('pending')
                ->and($registration?->source)->toBe('offline_admission')
                ->and($registration?->payment_status)->toBe('pending')
                ->and($registration?->metadata)->toBe(['offline_admission' => true])
                ->and($registration?->items->first()?->status)->toBe('pending')
                ->and($registration?->passes)->toBeEmpty();
        } finally {
            Relation::morphMap($originalMorphMap, false);
        }
    });
});

it('does not issue a duplicate pass when event registration pass issuance is retried', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $originalMorphMap = Relation::morphMap();
        Relation::morphMap(['test-order' => Order::class], false);

        try {
            $event = Event::factory()->paid()->create();
            $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
            $ticketType = createEventTicketType($occurrence, ['price' => 1500]);
            $order = Order::factory()->create();
            $orderItem = OrderItem::query()->create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'purchasable_type' => $ticketType->getMorphClass(),
                'purchasable_id' => $ticketType->id,
                'name' => 'Event Ticket',
                'sku' => 'EVENT-TICKET',
                'quantity' => 1,
                'unit_price' => 1500,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'currency' => 'MYR',
            ]);

            $registration = app(CreateRegistrationsFromOrderAction::class)->handle(
                target: $occurrence,
                orderItem: $orderItem->fresh(),
                participants: [['name' => 'Pass Holder']],
            )->firstOrFail();

            $first = app(IssueEventRegistrationPassesAction::class)->handle($registration);
            $second = app(IssueEventRegistrationPassesAction::class)->handle($registration->fresh());

            expect($first)->toHaveCount(1)
                ->and($second)->toHaveCount(1)
                ->and($registration->passes()->count())->toBe(1);
        } finally {
            Relation::morphMap($originalMorphMap, false);
        }
    });
});

it('creates order-linked registrations for free ticket items and defers pass issuance', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $originalMorphMap = Relation::morphMap();
        Relation::morphMap(['test-order' => Order::class], false);

        try {
            $event = Event::factory()->free()->create();
            $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
            $ticketType = createEventTicketType($occurrence, ['price' => 0]);
            $order = Order::factory()->create();
            $orderItem = OrderItem::query()->create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'purchasable_type' => $ticketType->getMorphClass(),
                'purchasable_id' => $ticketType->id,
                'name' => 'Free Event Ticket',
                'sku' => 'FREE-EVENT-TICKET',
                'quantity' => 1,
                'unit_price' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'currency' => 'MYR',
            ]);

            $registrations = app(CreateRegistrationsFromOrderAction::class)->handle(
                target: $occurrence,
                orderItem: $orderItem->fresh(),
                participants: [['name' => 'Free Attendee', 'is_purchaser' => true]],
            );

            $registration = $registrations->first();

            expect($registrations)->toHaveCount(1)
                ->and($registration?->source)->toBe('order')
                ->and($registration?->status->getValue())->toBe('confirmed')
                ->and($registration?->external_order_id)->toBe($order->id)
                ->and($registration?->items)->toHaveCount(1)
                ->and($registration?->items->first()?->external_order_item_id)->toBe($orderItem->id)
                ->and($registration?->passes)->toBeEmpty();
        } finally {
            Relation::morphMap($originalMorphMap, false);
        }
    });
});

it('returns existing registrations when replaying a paid order item after capacity is exhausted', function (): void {
    config()->set('events.features.enforce_scope_capacity_on_paid_registrations', true);

    OwnerContext::withOwner(null, function (): void {
        $originalMorphMap = Relation::morphMap();
        Relation::morphMap(['test-order' => Order::class], false);

        try {
            $event = Event::factory()->paid()->create();
            $occurrence = EventOccurrence::factory()->create([
                'event_id' => $event->id,
                'capacity' => 1,
            ]);
            $ticketType = createEventTicketType($event, ['price' => 1500]);
            $order = Order::factory()->create();
            $orderItem = OrderItem::query()->create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'purchasable_type' => $ticketType->getMorphClass(),
                'purchasable_id' => $ticketType->id,
                'name' => 'Event Ticket',
                'sku' => 'EVENT-TICKET',
                'quantity' => 1,
                'unit_price' => 1500,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'currency' => 'MYR',
            ]);

            $participants = [
                ['name' => 'Alice Example', 'email' => 'alice@example.com'],
            ];

            $firstPass = app(CreateRegistrationsFromOrderAction::class)->handle(
                target: $occurrence,
                orderItem: $orderItem->fresh(),
                participants: $participants,
            );

            $replayed = app(CreateRegistrationsFromOrderAction::class)->handle(
                target: $occurrence,
                orderItem: $orderItem->fresh(),
                participants: $participants,
            );

            expect($firstPass)->toHaveCount(1)
                ->and($replayed)->toHaveCount(1)
                ->and($replayed->first()?->is($firstPass->first()))->toBeTrue();
        } finally {
            Relation::morphMap($originalMorphMap, false);
        }
    });
});

it('creates registrations for occurrence-scoped ticket types on an occurrence', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $originalMorphMap = Relation::morphMap();
        Relation::morphMap(['test-order' => Order::class], false);

        try {
            $event = Event::factory()->paid()->create();
            $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
            $ticketType = createEventTicketType($occurrence, ['price' => 1500]);
            $order = Order::factory()->create();
            $orderItem = OrderItem::query()->create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'purchasable_type' => $ticketType->getMorphClass(),
                'purchasable_id' => $ticketType->id,
                'name' => 'Event Ticket',
                'sku' => 'EVENT-TICKET',
                'quantity' => 1,
                'unit_price' => 1500,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'currency' => 'MYR',
            ]);

            $registrations = app(CreateRegistrationsFromOrderAction::class)->handle(
                target: $occurrence,
                orderItem: $orderItem->fresh(),
                participants: [
                    ['name' => 'Alice Example', 'email' => 'alice@example.com'],
                ],
            );

            expect($registrations)->toHaveCount(1);
            expect($registrations->first()?->items)->toHaveCount(1)
                ->and($registrations->first()?->items->first()?->ticket_type_id)->toBe($ticketType->id);
        } finally {
            Relation::morphMap($originalMorphMap, false);
        }
    });
});

it('creates registrations for session-scoped ticket types on a session', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $originalMorphMap = Relation::morphMap();
        Relation::morphMap(['test-order' => Order::class], false);

        try {
            $event = Event::factory()->paid()->create();
            $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
            $session = EventSession::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
            ]);
            $ticketType = createEventTicketType($session, ['price' => 1500]);
            $order = Order::factory()->create();
            $orderItem = OrderItem::query()->create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'purchasable_type' => $ticketType->getMorphClass(),
                'purchasable_id' => $ticketType->id,
                'name' => 'Session Ticket',
                'sku' => 'SESSION-TICKET',
                'quantity' => 1,
                'unit_price' => 1500,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'currency' => 'MYR',
            ]);

            $registrations = app(CreateRegistrationsFromOrderAction::class)->handle(
                target: $session,
                orderItem: $orderItem->fresh(),
                participants: [
                    ['name' => 'Alice Example', 'email' => 'alice@example.com'],
                ],
            );

            expect($registrations)->toHaveCount(1);
            expect($registrations->first()?->items)->toHaveCount(1)
                ->and($registrations->first()?->items->first()?->ticket_type_id)->toBe($ticketType->id);
        } finally {
            Relation::morphMap($originalMorphMap, false);
        }
    });
});

it('rejects occurrence-scoped ticket types when targeting the event', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $originalMorphMap = Relation::morphMap();
        Relation::morphMap(['test-order' => Order::class], false);

        try {
            $event = Event::factory()->paid()->create();
            $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
            $ticketType = createEventTicketType($occurrence, ['price' => 1500]);
            $order = Order::factory()->create();
            $orderItem = OrderItem::query()->create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'purchasable_type' => $ticketType->getMorphClass(),
                'purchasable_id' => $ticketType->id,
                'name' => 'Event Ticket',
                'sku' => 'EVENT-TICKET',
                'quantity' => 1,
                'unit_price' => 1500,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'currency' => 'MYR',
            ]);

            app(CreateRegistrationsFromOrderAction::class)->handle(
                target: $event,
                orderItem: $orderItem->fresh(),
                participants: [
                    ['name' => 'Alice Example', 'email' => 'alice@example.com'],
                ],
            );
        } finally {
            Relation::morphMap($originalMorphMap, false);
        }
    });
})->throws(InvalidArgumentException::class, 'same event scope');

it('rejects session-scoped ticket types when targeting an occurrence', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $originalMorphMap = Relation::morphMap();
        Relation::morphMap(['test-order' => Order::class], false);

        try {
            $event = Event::factory()->paid()->create();
            $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
            $session = EventSession::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
            ]);
            $ticketType = createEventTicketType($session, ['price' => 1500]);
            $order = Order::factory()->create();
            $orderItem = OrderItem::query()->create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'purchasable_type' => $ticketType->getMorphClass(),
                'purchasable_id' => $ticketType->id,
                'name' => 'Session Ticket',
                'sku' => 'SESSION-TICKET',
                'quantity' => 1,
                'unit_price' => 1500,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'currency' => 'MYR',
            ]);

            app(CreateRegistrationsFromOrderAction::class)->handle(
                target: $occurrence,
                orderItem: $orderItem->fresh(),
                participants: [
                    ['name' => 'Alice Example', 'email' => 'alice@example.com'],
                ],
            );
        } finally {
            Relation::morphMap($originalMorphMap, false);
        }
    });
})->throws(InvalidArgumentException::class, 'same event scope');
