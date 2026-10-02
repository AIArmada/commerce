<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\CreateRegistrationsFromOrderAction;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationItem;
use AIArmada\Events\Models\EventRegistrationParticipant;
use AIArmada\Events\Models\EventSession;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Ticketing\Enums\PricingMode;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Support\Str;

if (! function_exists('createMixedScopeOrderItem')) {
    function createMixedScopeOrderItem(TicketType $ticketType, Order $order, array $overrides = []): OrderItem
    {
        return OrderItem::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'purchasable_type' => $ticketType->getMorphClass(),
            'purchasable_id' => $ticketType->id,
            'name' => $ticketType->name,
            'sku' => 'TICKET-' . Str::upper(Str::random(8)),
            'quantity' => 1,
            'unit_price' => $ticketType->price ?? 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'currency' => $ticketType->currency ?? 'MYR',
        ], $overrides));
    }
}

beforeEach(function (): void {
    config()->set('events.features.free_only.auto_derive_pricing_from_ticket_types', true);
    config()->set('events.features.owner.enabled', true);
    config()->set('events.features.enforce_scope_capacity_on_paid_registrations', false);
});

it('enforces occurrence capacity for a zero-price ticket in a mixed scope', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 1,
        ]);
        $freeTicket = createEventTicketType($occurrence, ['price' => 0]);
        createEventTicketType($occurrence, ['price' => 1500]);

        expect($occurrence->fresh()?->effectivePricingMode())->toBe(PricingMode::Mixed);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);

        $order = Order::factory()->create();
        $orderItem = createMixedScopeOrderItem($freeTicket, $order);

        expect(fn () => app(CreateRegistrationsFromOrderAction::class)->handle(
            target: $occurrence,
            orderItem: $orderItem->fresh(),
            participants: [['name' => 'Free Attendee']],
        ))->toThrow(EventCapacityExceededException::class);

        expect(EventRegistration::query()->where('event_occurrence_id', $occurrence->id)->count())->toBe(1)
            ->and(EventRegistrationItem::query()->count())->toBe(0)
            ->and(EventRegistrationParticipant::query()->count())->toBe(0)
            ->and(Pass::query()->count())->toBe(0)
            ->and($occurrence->fresh()?->capacityRemaining())->toBe(0);
    });
});

it('enforces parent capacity for a zero-price session ticket when a sibling consumed the aggregate', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 1,
        ]);
        $sibling = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 5,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 5,
        ]);
        $freeTicket = createEventTicketType($session, ['price' => 0]);
        createEventTicketType($session, ['price' => 1500]);

        expect($session->fresh()?->effectivePricingMode())->toBe(PricingMode::Mixed);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $sibling->id,
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);

        $order = Order::factory()->create();
        $orderItem = createMixedScopeOrderItem($freeTicket, $order);

        expect(fn () => app(CreateRegistrationsFromOrderAction::class)->handle(
            target: $session,
            orderItem: $orderItem->fresh(),
            participants: [['name' => 'Free Attendee']],
        ))->toThrow(EventCapacityExceededException::class);

        expect(EventRegistration::query()->where('event_occurrence_id', $occurrence->id)->count())->toBe(1)
            ->and(EventRegistrationItem::query()->count())->toBe(0)
            ->and(EventRegistrationParticipant::query()->count())->toBe(0)
            ->and(Pass::query()->count())->toBe(0);
    });
});

it('enforces session capacity for a zero-price session ticket in a mixed scope', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 5,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 1,
        ]);
        $freeTicket = createEventTicketType($session, ['price' => 0]);
        createEventTicketType($session, ['price' => 1500]);

        expect($session->fresh()?->effectivePricingMode())->toBe(PricingMode::Mixed);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);

        $order = Order::factory()->create();
        $orderItem = createMixedScopeOrderItem($freeTicket, $order);

        expect(fn () => app(CreateRegistrationsFromOrderAction::class)->handle(
            target: $session,
            orderItem: $orderItem->fresh(),
            participants: [['name' => 'Free Attendee']],
        ))->toThrow(EventCapacityExceededException::class);

        expect(EventRegistration::query()->where('event_session_id', $session->id)->count())->toBe(1)
            ->and(EventRegistrationItem::query()->count())->toBe(0)
            ->and(EventRegistrationParticipant::query()->count())->toBe(0)
            ->and(Pass::query()->count())->toBe(0);
    });
});

it('creates free-ticket order registrations in a mixed scope when capacity remains', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 2,
        ]);
        $freeTicket = createEventTicketType($occurrence, ['price' => 0]);
        createEventTicketType($occurrence, ['price' => 1500]);

        expect($occurrence->fresh()?->effectivePricingMode())->toBe(PricingMode::Mixed);

        $order = Order::factory()->create();
        $orderItem = createMixedScopeOrderItem($freeTicket, $order);

        $registrations = app(CreateRegistrationsFromOrderAction::class)->handle(
            target: $occurrence,
            orderItem: $orderItem->fresh(),
            participants: [['name' => 'Free Attendee', 'is_purchaser' => true]],
        );

        $registration = $registrations->first();

        expect($registrations)->toHaveCount(1)
            ->and($registration?->status->getValue())->toBe('confirmed')
            ->and($registration?->source)->toBe('order')
            ->and($registration?->payment_status)->toBe('free')
            ->and($registration?->external_order_id)->toBe($order->id)
            ->and($registration?->items)->toHaveCount(1)
            ->and($registration?->items->first()?->external_order_item_id)->toBe($orderItem->id)
            ->and($registration?->passes)->toBeEmpty();
    });
});

it('keeps a paid line discounted to zero under configured enforcement in a mixed scope', function (): void {
    config()->set('events.features.enforce_scope_capacity_on_paid_registrations', true);

    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 1,
        ]);
        createEventTicketType($occurrence, ['price' => 0]);
        $paidTicket = createEventTicketType($occurrence, ['price' => 1500]);

        expect($occurrence->fresh()?->effectivePricingMode())->toBe(PricingMode::Mixed);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);

        $order = Order::factory()->create();
        $orderItem = createMixedScopeOrderItem($paidTicket, $order, [
            'unit_price' => 1500,
            'discount_amount' => 1500,
            'total' => 0,
        ]);

        expect(fn () => app(CreateRegistrationsFromOrderAction::class)->handle(
            target: $occurrence,
            orderItem: $orderItem->fresh(),
            participants: [['name' => 'Discounted Attendee']],
        ))->toThrow(EventCapacityExceededException::class);

        expect(EventRegistration::query()->where('event_occurrence_id', $occurrence->id)->count())->toBe(1)
            ->and(EventRegistrationItem::query()->count())->toBe(0)
            ->and(EventRegistrationParticipant::query()->count())->toBe(0)
            ->and(Pass::query()->count())->toBe(0);
    });
});

it('keeps the configured paid bypass for a discounted-to-zero line when enforcement is off', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 1,
        ]);
        createEventTicketType($occurrence, ['price' => 0]);
        $paidTicket = createEventTicketType($occurrence, ['price' => 1500]);

        expect($occurrence->fresh()?->effectivePricingMode())->toBe(PricingMode::Mixed);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);

        $order = Order::factory()->create();
        $orderItem = createMixedScopeOrderItem($paidTicket, $order, [
            'unit_price' => 1500,
            'discount_amount' => 1500,
            'total' => 0,
        ]);

        $registrations = app(CreateRegistrationsFromOrderAction::class)->handle(
            target: $occurrence,
            orderItem: $orderItem->fresh(),
            participants: [['name' => 'Discounted Attendee']],
        );

        $registration = $registrations->first();

        expect($registrations)->toHaveCount(1)
            ->and($registration?->status->getValue())->toBe('confirmed')
            ->and($registration?->total_amount)->toBe(0)
            ->and($registration?->payment_status)->toBeNull();
    });
});

it('honors paid options for a discounted-to-zero line when enforcement is on and room remains', function (): void {
    config()->set('events.features.enforce_scope_capacity_on_paid_registrations', true);

    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 2,
        ]);
        createEventTicketType($occurrence, ['price' => 0]);
        $paidTicket = createEventTicketType($occurrence, ['price' => 1500]);

        expect($occurrence->fresh()?->effectivePricingMode())->toBe(PricingMode::Mixed);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);

        $order = Order::factory()->create();
        $orderItem = createMixedScopeOrderItem($paidTicket, $order, [
            'unit_price' => 1500,
            'discount_amount' => 1500,
            'total' => 0,
        ]);

        $registrations = app(CreateRegistrationsFromOrderAction::class)->handle(
            target: $occurrence,
            orderItem: $orderItem->fresh(),
            participants: [['name' => 'Discounted Attendee']],
            options: [
                'registration_status' => 'pending',
                'item_status' => 'pending',
                'payment_status' => 'pending',
            ],
        );

        $registration = $registrations->first();

        expect($registrations)->toHaveCount(1)
            ->and($registration?->status->getValue())->toBe('pending')
            ->and($registration?->payment_status)->toBe('pending')
            ->and($registration?->items->first()?->status)->toBe('pending');
    });
});
