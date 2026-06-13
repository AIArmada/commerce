<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationItem;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Resolvers\DefaultEventOrderItemFulfillmentResolver;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('orders.owner.enabled', false);
    config()->set('contacting.features.owner.enabled', false);
});

it('resolves order item fulfillment payloads from the registration participants', function (): void {
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

        $participant = $registration->participants()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'name' => 'Alice Example',
            'is_primary' => true,
        ]);
        $participant->addContactMethod(ContactMethodData::email('alice@example.com'));
        $participant->addContactMethod(ContactMethodData::phone('+60111222333', countryCode: 'MY'));

        $registrationItem = EventRegistrationItem::query()->create([
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

        $payload = app(DefaultEventOrderItemFulfillmentResolver::class)->resolve($registrationItem);

        expect($payload)->toBe([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'ticket_type_id' => $ticketType->id,
            'participants' => [
                [
                    'name' => 'Alice Example',
                    'email' => 'alice@example.com',
                    'phone' => '+60111222333',
                    'is_primary' => true,
                ],
            ],
        ]);
    });
});
