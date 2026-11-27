<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Events\Contracts\EventCartDriver;
use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Data\EventCartLineData;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Commerce\Actions\CreateOccurrenceCartLineAction;
use AIArmada\Events\Features\Commerce\Actions\EnsureTicketTypeForOccurrenceAction;
use AIArmada\Events\Features\Commerce\Actions\FinalizeOccurredEventOrdersAction;
use AIArmada\Events\Features\Commerce\Actions\StartOccurrenceCheckoutAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Platform\EventTicketScope;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Ticketing\Data\TicketTypeData;
use AIArmada\Ticketing\Models\TicketType;

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

    $ticketTypeId = app(EnsureTicketTypeForOccurrenceAction::class)->handle('session', (string) $session->getKey(), [
        'name' => 'Session Access',
        'price' => 2500,
    ]);

    $ticketType = TicketType::query()->findOrFail($ticketTypeId);

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

    $ticketTypeId = app(EnsureTicketTypeForOccurrenceAction::class)->handle('session', (string) $session->getKey(), [
        'name' => 'Session Access',
        'price' => 2500,
    ]);

    $ticketType = TicketType::query()->findOrFail($ticketTypeId);

    $level = InventoryLevel::query()
        ->where('inventoryable_type', $ticketType->getMorphClass())
        ->where('inventoryable_id', $ticketType->getKey())
        ->where('location_id', $defaultLocation->id)
        ->firstOrFail();

    expect($level->quantity_on_hand)->toBe(10)
        ->and($level->quantity_reserved)->toBe(0);
    expect(TicketTypeData::fromTicketType($ticketType)->quota)->toBe(10);

    $level->incrementReserved(4);

    app(EnsureTicketTypeForOccurrenceAction::class)->handle('session', (string) $session->getKey(), [
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

    $capturedLine = null;
    $cartDriver = Mockery::mock(EventCartDriver::class);
    $cartDriver->shouldReceive('addLine')
        ->once()
        ->withArgs(function (Cart $cart, EventCartLineData $line) use (&$capturedLine, $ticketType): bool {
            $capturedLine = $line;

            return $line->ticketTypeId === (string) $ticketType->getKey()
                && $line->quantity === 2
                && ($line->attributes['participants'] ?? null) === [['name' => 'Alice']];
        });
    $cartDriver->shouldReceive('addRequiredBundles')->once();

    app()->instance(CartManagerInterface::class, $cartManager);
    app()->instance(EventCartDriver::class, $cartDriver);

    app(CreateOccurrenceCartLineAction::class)->handle('session', (string) $session->getKey(), [
        'ticket_type_id' => $ticketType->id,
        'quantity' => 2,
        'participants' => [
            ['name' => 'Alice'],
        ],
    ]);

    expect($capturedLine)->toBeInstanceOf(EventCartLineData::class);
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
        ->with('session', (string) $session->getKey(), (string) $registration->getKey())
        ->andReturn('checkout-session');

    app()->instance(EventCheckoutIntentResolver::class, $resolver);

    expect(app(StartOccurrenceCheckoutAction::class)->handle('session', (string) $session->getKey(), (string) $registration->getKey()))->toBe('checkout-session');
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
    $ticketType = createEventTicketType($session);
    $item = $registration->items()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 1,
        'unit_price' => 0,
        'total_price' => 0,
        'currency' => 'MYR',
        'status' => 'confirmed',
    ]);

    $resolver = Mockery::mock(EventOrderItemFulfillmentResolver::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with((string) $item->getKey());

    app()->instance(EventOrderItemFulfillmentResolver::class, $resolver);

    app(FinalizeOccurredEventOrdersAction::class)->handle('session', (string) $session->getKey());
});
