<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\CreateRegistrationsFromOrderAction;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTicketType;
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
            $ticketType = EventTicketType::factory()->create(['event_id' => $event->id, 'price' => 1500]);
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

it('creates registrations for occurrence-scoped ticket types on an occurrence', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $originalMorphMap = Relation::morphMap();
        Relation::morphMap(['test-order' => Order::class], false);

        try {
            $event = Event::factory()->paid()->create();
            $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
            $ticketType = EventTicketType::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'price' => 1500,
            ]);
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
                ->and($registrations->first()?->items->first()?->event_ticket_type_id)->toBe($ticketType->id);
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
            $event = Event::factory()->create();
            $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
            $session = EventSession::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
            ]);
            $ticketType = EventTicketType::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'event_session_id' => $session->id,
                'price' => 1500,
            ]);
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
                ->and($registrations->first()?->items->first()?->event_ticket_type_id)->toBe($ticketType->id);
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
            $ticketType = EventTicketType::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'price' => 1500,
            ]);
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
            $event = Event::factory()->create();
            $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
            $session = EventSession::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
            ]);
            $ticketType = EventTicketType::factory()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'event_session_id' => $session->id,
                'price' => 1500,
            ]);
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
