<?php

declare(strict_types=1);

use AIArmada\Events\Actions\CreateRegistrationsForOrderItemAction;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Support\Str;

it('creates registrations for an order item using the order model as the external order type', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $ticketType = EventTicketType::factory()->create(['event_id' => $event->id]);
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

    $registrations = app(CreateRegistrationsForOrderItemAction::class)->handle($occurrence, $orderItem->fresh(), [
        ['name' => 'Alice Example', 'email' => 'alice@example.com'],
        ['name' => 'Bob Example', 'email' => 'bob@example.com'],
    ]);

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
});
