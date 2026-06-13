<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventPassDeliveryService;
use AIArmada\Events\Contracts\EventPassIssuer;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationItem;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Steps\IssueEventPassesStep;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('checkout.owner.enabled', false);
    config()->set('orders.owner.enabled', false);
    config()->set('customers.features.owner.enabled', false);
});

it('issues and delivers all passes created for matching registrations', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'timezone' => 'UTC',
            'delivery_mode' => 'in_person',
        ]);
        $ticketType = EventTicketType::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
        ]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::query()->create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'purchasable_type' => EventTicketType::class,
            'purchasable_id' => $ticketType->id,
            'name' => $ticketType->name,
            'sku' => 'EVENT-TICKET',
            'quantity' => 1,
            'unit_price' => 1500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => 'MYR',
        ]);

        $registration = EventRegistration::query()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'registration_type' => 'individual',
            'status' => 'confirmed',
            'source' => 'order',
            'total_participants' => 1,
            'currency' => 'MYR',
            'external_order_id' => $order->id,
            'external_order_type' => Order::class,
        ]);

        EventRegistrationItem::query()->create([
            'event_registration_id' => $registration->id,
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 1500,
            'total_price' => 1500,
            'currency' => 'MYR',
            'status' => 'confirmed',
            'external_order_item_id' => $orderItem->id,
            'external_order_item_type' => OrderItem::class,
        ]);

        $firstPass = EventPass::factory()->make([
            'id' => (string) Str::uuid(),
            'pass_no' => 'PASS-001',
        ]);
        $secondPass = EventPass::factory()->make([
            'id' => (string) Str::uuid(),
            'pass_no' => 'PASS-002',
        ]);

        $issuer = mock(EventPassIssuer::class);
        $issuer->shouldReceive('issuePassesFor')
            ->once()
            ->withArgs(fn (EventRegistration $model): bool => $model->is($registration))
            ->andReturn([$firstPass, $secondPass]);

        $delivery = mock(EventPassDeliveryService::class);
        $delivery->shouldReceive('deliver')->once()->with($firstPass);
        $delivery->shouldReceive('deliver')->once()->with($secondPass);

        $session = CheckoutSession::query()->create([
            'cart_id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'currency' => 'MYR',
            'cart_snapshot' => [],
        ]);

        $result = app(IssueEventPassesStep::class)->handle($session);

        expect($result->isSuccessful())->toBeTrue()
            ->and($result->message)->toBe('2 passes issued.');
    });
});

it('only issues passes once for duplicate ticket type order items', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'timezone' => 'UTC',
            'delivery_mode' => 'in_person',
        ]);
        $ticketType = EventTicketType::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
        ]);
        $order = Order::factory()->create();
        $firstOrderItem = OrderItem::query()->create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'purchasable_type' => EventTicketType::class,
            'purchasable_id' => $ticketType->id,
            'name' => $ticketType->name,
            'sku' => 'EVENT-TICKET-1',
            'quantity' => 1,
            'unit_price' => 1500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => 'MYR',
        ]);
        $secondOrderItem = OrderItem::query()->create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'purchasable_type' => EventTicketType::class,
            'purchasable_id' => $ticketType->id,
            'name' => $ticketType->name,
            'sku' => 'EVENT-TICKET-2',
            'quantity' => 1,
            'unit_price' => 1500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => 'MYR',
        ]);

        $firstRegistration = EventRegistration::query()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'registration_type' => 'individual',
            'status' => 'confirmed',
            'source' => 'order',
            'total_participants' => 1,
            'currency' => 'MYR',
            'external_order_id' => $order->id,
            'external_order_type' => Order::class,
        ]);

        EventRegistrationItem::query()->create([
            'event_registration_id' => $firstRegistration->id,
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 1500,
            'total_price' => 1500,
            'currency' => 'MYR',
            'status' => 'confirmed',
            'external_order_item_id' => $firstOrderItem->id,
            'external_order_item_type' => OrderItem::class,
        ]);

        $secondRegistration = EventRegistration::query()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'registration_type' => 'individual',
            'status' => 'confirmed',
            'source' => 'order',
            'total_participants' => 1,
            'currency' => 'MYR',
            'external_order_id' => $order->id,
            'external_order_type' => Order::class,
        ]);

        EventRegistrationItem::query()->create([
            'event_registration_id' => $secondRegistration->id,
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 1500,
            'total_price' => 1500,
            'currency' => 'MYR',
            'status' => 'confirmed',
            'external_order_item_id' => $secondOrderItem->id,
            'external_order_item_type' => OrderItem::class,
        ]);

        $firstPass = EventPass::factory()->make([
            'id' => (string) Str::uuid(),
            'pass_no' => 'PASS-101',
        ]);
        $secondPass = EventPass::factory()->make([
            'id' => (string) Str::uuid(),
            'pass_no' => 'PASS-102',
        ]);

        $issuer = mock(EventPassIssuer::class);
        $issuer->shouldReceive('issuePassesFor')
            ->once()
            ->withArgs(fn (EventRegistration $model): bool => $model->is($firstRegistration))
            ->andReturn([$firstPass]);
        $issuer->shouldReceive('issuePassesFor')
            ->once()
            ->withArgs(fn (EventRegistration $model): bool => $model->is($secondRegistration))
            ->andReturn([$secondPass]);

        $delivery = mock(EventPassDeliveryService::class);
        $delivery->shouldReceive('deliver')->once()->with($firstPass);
        $delivery->shouldReceive('deliver')->once()->with($secondPass);

        $session = CheckoutSession::query()->create([
            'cart_id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'currency' => 'MYR',
            'cart_snapshot' => [],
        ]);

        $result = app(IssueEventPassesStep::class)->handle($session);

        expect($result->isSuccessful())->toBeTrue()
            ->and($result->message)->toBe('2 passes issued.');
    });
});
