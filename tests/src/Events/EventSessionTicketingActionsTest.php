<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Events\Actions\AddEventTicketTypeToCartAction;
use AIArmada\Events\Actions\CreateOccurrenceCartLineAction;
use AIArmada\Events\Actions\EnsureTicketTypeForOccurrenceAction;
use AIArmada\Events\Actions\FinalizeOccurredEventOrdersAction;
use AIArmada\Events\Actions\FulfillEventOrderAction;
use AIArmada\Events\Actions\StartOccurrenceCheckoutAction;
use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Data\TicketTypeData;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\EventTicketScope;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('checkout.owner.enabled', false);
    config()->set('orders.owner.enabled', false);
    config()->set('inventory.owner.enabled', false);
});

afterEach(function (): void {
    if (class_exists(Mockery::class)) {
        Mockery::close();
    }
});

it('ensures ticket types for sessions', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'status' => 'published',
    ]);

    $ticketType = app(EnsureTicketTypeForOccurrenceAction::class)->handle($session, [
        'name' => 'Session Access',
        'price' => 2500,
    ]);

    expect($ticketType->ticketable?->is($session))->toBeTrue()
        ->and(EventTicketScope::ids($ticketType))->toBe([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
        ])
        ->and($ticketType->code)->toBe($session->id)
        ->and(TicketTypeData::fromTicketType($ticketType)->quota)->toBe($session->capacity);
});

it('syncs session ticket quotas to the default inventory location', function (): void {
    $defaultLocation = InventoryLocation::getOrCreateDefault();

    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'status' => 'published',
        'capacity' => 10,
    ]);

    $ticketType = app(EnsureTicketTypeForOccurrenceAction::class)->handle($session, [
        'name' => 'Session Access',
        'price' => 2500,
    ]);

    $level = InventoryLevel::query()
        ->where('inventoryable_type', $ticketType->getMorphClass())
        ->where('inventoryable_id', $ticketType->getKey())
        ->where('location_id', $defaultLocation->id)
        ->firstOrFail();

    expect($level->quantity_on_hand)->toBe(10)
        ->and($level->quantity_reserved)->toBe(0);
    expect(TicketTypeData::fromTicketType($ticketType)->quota)->toBe(10);

    $level->update(['quantity_reserved' => 4]);

    app(EnsureTicketTypeForOccurrenceAction::class)->handle($session, [
        'name' => 'Session Access',
        'price' => 2500,
        'quota' => 14,
    ]);

    $level->refresh();

    expect($level->quantity_on_hand)->toBe(14)
        ->and($level->quantity_reserved)->toBe(4)
        ->and(TicketTypeData::fromTicketType($ticketType)->quota)->toBe(14);
});

it('adds session ticket types to the cart', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'status' => 'published',
    ]);
    $ticketType = createEventTicketType($session, ['status' => 'active']);

    $cart = app(CartManagerInterface::class)->getCurrentCart();
    $cartManager = Mockery::mock(CartManagerInterface::class);
    $cartManager->shouldReceive('getCurrentCart')->once()->andReturn($cart);

    $cartItem = new CartItem(
        $ticketType->id,
        $ticketType->name,
        $ticketType->price,
        2,
        ['participants' => [['name' => 'Alice']]],
        [],
        $ticketType,
    );
    $addToCart = new class($cartItem)
    {
        public bool $called = false;

        public function __construct(private readonly CartItem $cartItem) {}

        public function handle(...$arguments): CartItem
        {
            $this->called = true;

            return $this->cartItem;
        }
    };

    app()->instance(CartManagerInterface::class, $cartManager);
    app()->instance(AddEventTicketTypeToCartAction::class, $addToCart);

    $result = app(CreateOccurrenceCartLineAction::class)->handle($session, [
        'ticket_type_id' => $ticketType->id,
        'quantity' => 2,
        'participants' => [
            ['name' => 'Alice'],
        ],
    ]);

    expect($result)->toBe($cartItem)
        ->and($addToCart->called)->toBeTrue();
});

it('starts checkout for sessions', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'status' => 'published',
    ]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
    ]);

    $resolver = Mockery::mock(EventCheckoutIntentResolver::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with($session, $registration)
        ->andReturn('checkout-session');

    app()->instance(EventCheckoutIntentResolver::class, $resolver);

    expect(app(StartOccurrenceCheckoutAction::class)->handle($session, $registration))->toBe('checkout-session');
});

it('finalizes session orders', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'status' => 'published',
    ]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
        'external_order_id' => 'order-123',
    ]);

    $fulfillOrder = new class
    {
        public bool $called = false;

        public ?EventRegistration $registration = null;

        public function handle(EventRegistration $registration): void
        {
            $this->called = true;
            $this->registration = $registration;
        }
    };

    app()->instance(FulfillEventOrderAction::class, $fulfillOrder);

    app(FinalizeOccurredEventOrdersAction::class)->handle($session);

    expect($fulfillOrder->called)->toBeTrue()
        ->and($fulfillOrder->registration?->is($registration))->toBeTrue();
});
