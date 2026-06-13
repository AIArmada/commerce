<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Resolvers\DefaultEventCheckoutIntentResolver;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('checkout.owner.enabled', false);
    config()->set('orders.owner.enabled', false);
    config()->set('customers.features.owner.enabled', false);
});

it('preserves the full participant payload when resolving an event checkout intent', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $customer = Customer::create([
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'email' => 'jordan@example.com',
            'phone' => '+60123456789',
            'is_guest' => false,
        ]);
        $customer->addContactMethod(ContactMethodData::email('jordan@example.com'));

        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
        ]);
        $ticketType = EventTicketType::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
        ]);

        $registration = app(RegistrationServiceInterface::class)->register([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'registrant_type' => $customer->getMorphClass(),
            'registrant_id' => $customer->id,
            'registration_type' => 'individual',
            'status' => 'confirmed',
            'source' => 'website',
            'total_participants' => 1,
            'participants' => [
                [
                    'name' => 'Alice Example',
                    'email' => 'alice@example.com',
                    'phone' => '+60111222333',
                    'relationship_to_registrant' => 'spouse',
                    'age' => 29,
                    'gender' => 'female',
                    'is_primary' => true,
                ],
            ],
            'items' => [
                [
                    'event_ticket_type_id' => $ticketType->id,
                    'quantity' => 1,
                    'unit_price' => $ticketType->price,
                    'total_price' => $ticketType->price,
                    'currency' => $ticketType->currency,
                    'status' => 'confirmed',
                ],
            ],
        ]);

        $registration->load('items.ticketType', 'participants', 'registrant');

        $checkoutSession = app(DefaultEventCheckoutIntentResolver::class)->resolve($occurrence, $registration);

        expect($checkoutSession)->toBeInstanceOf(CheckoutSession::class)
            ->and($checkoutSession->customer_id)->toBe($customer->id);

        $cartItems = array_values($checkoutSession->cart_snapshot['items'] ?? []);

        expect($cartItems)->toHaveCount(1)
            ->and($cartItems[0]['id'] ?? null)->toBe($ticketType->id)
            ->and($cartItems[0]['quantity'] ?? null)->toBe(1)
            ->and($cartItems[0]['attributes']['participants'] ?? null)->toBe([
                [
                    'name' => 'Alice Example',
                    'email' => 'alice@example.com',
                    'phone' => '+60111222333',
                    'relationship_to_registrant' => 'spouse',
                    'event_occurrence_id' => $occurrence->id,
                    'age' => 29,
                    'gender' => 'female',
                    'status' => 'active',
                    'metadata' => [
                        'contact' => [
                            'email' => 'alice@example.com',
                            'phone' => '+60111222333',
                        ],
                    ],
                    'is_primary' => true,
                ],
            ]);
    });
});
