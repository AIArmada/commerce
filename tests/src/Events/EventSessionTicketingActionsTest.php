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
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTicketType;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('checkout.owner.enabled', false);
    config()->set('orders.owner.enabled', false);
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

    expect($ticketType->event_id)->toBe($event->id)
        ->and($ticketType->event_occurrence_id)->toBeNull()
        ->and($ticketType->event_session_id)->toBe($session->id)
        ->and($ticketType->code)->toBe($session->id)
        ->and($ticketType->quota)->toBe($session->capacity);
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
    $ticketType = EventTicketType::factory()->create([
        'event_id' => $event->id,
        'event_session_id' => $session->id,
        'status' => 'active',
    ]);

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
        'event_ticket_type_id' => $ticketType->id,
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
