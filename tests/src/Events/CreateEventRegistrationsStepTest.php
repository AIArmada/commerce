<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Steps\CreateEventRegistrationsStep;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', true);
    config()->set('checkout.owner.enabled', false);
    config()->set('orders.owner.enabled', true);
    config()->set('customers.features.owner.enabled', false);
});

it('reuses snapshot participants and falls back to the registrant when needed', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $customer = Customer::create([
            'first_name' => 'Maya',
            'last_name' => 'Jones',
            'email' => 'maya@example.com',
            'phone' => '+60111222333',
            'is_guest' => false,
        ]);
        $customer->addContactMethod(ContactMethodData::email('maya@example.com'));
        $customer->addContactMethod(ContactMethodData::phone('+60111222333', countryCode: 'MY'));

        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
        ]);

        $snapshotTicketType = EventTicketType::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'price' => 1500,
        ]);

        $fallbackTicketType = EventTicketType::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'price' => 1500,
        ]);

        $order = Order::factory()->create([
            'customer_type' => $customer->getMorphClass(),
            'customer_id' => $customer->id,
        ]);

        OrderItem::query()->create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'purchasable_type' => $snapshotTicketType->getMorphClass(),
            'purchasable_id' => $snapshotTicketType->id,
            'name' => $snapshotTicketType->name,
            'sku' => 'SNAPSHOT-' . Str::upper(Str::random(8)),
            'quantity' => 1,
            'unit_price' => $snapshotTicketType->price,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => $snapshotTicketType->currency,
        ]);

        OrderItem::query()->create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'purchasable_type' => $fallbackTicketType->getMorphClass(),
            'purchasable_id' => $fallbackTicketType->id,
            'name' => $fallbackTicketType->name,
            'sku' => 'FALLBACK-' . Str::upper(Str::random(8)),
            'quantity' => 2,
            'unit_price' => $fallbackTicketType->price,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => $fallbackTicketType->currency,
        ]);

        $session = CheckoutSession::query()->create([
            'cart_id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'cart_snapshot' => [
                'items' => [
                    [
                        'id' => $snapshotTicketType->id,
                        'name' => $snapshotTicketType->name,
                        'price' => $snapshotTicketType->price,
                        'quantity' => 1,
                        'attributes' => [
                            'purchasable_id' => $snapshotTicketType->id,
                            'participants' => [
                                [
                                    'name' => 'Alice Example',
                                    'email' => 'alice@example.com',
                                    'phone' => '+60123456789',
                                    'relationship_to_registrant' => 'spouse',
                                    'age' => 29,
                                    'gender' => 'female',
                                    'is_primary' => true,
                                ],
                            ],
                        ],
                        'associated_model' => [
                            'class' => EventTicketType::class,
                            'id' => $snapshotTicketType->id,
                        ],
                    ],
                    [
                        'id' => $fallbackTicketType->id,
                        'name' => $fallbackTicketType->name,
                        'price' => $fallbackTicketType->price,
                        'quantity' => 2,
                        'attributes' => [
                            'purchasable_id' => $fallbackTicketType->id,
                        ],
                        'associated_model' => [
                            'class' => EventTicketType::class,
                            'id' => $fallbackTicketType->id,
                        ],
                    ],
                ],
            ],
        ]);

        $captured = [];

        $registrationService = mock(RegistrationServiceInterface::class);
        $registrationService->shouldReceive('register')
            ->times(3)
            ->andReturnUsing(function (array $data) use (&$captured): EventRegistration {
                $captured[] = [
                    'ticket_type_id' => $data['items'][0]['event_ticket_type_id'] ?? null,
                    'participant' => $data['participants'][0] ?? null,
                ];

                return new EventRegistration;
            });

        $step = app(CreateEventRegistrationsStep::class);
        $result = $step->handle($session->fresh());

        expect($result->isSuccessful())->toBeTrue();

        $capturedByTicketType = collect($captured)->groupBy('ticket_type_id');

        expect($capturedByTicketType->get($snapshotTicketType->id)?->pluck('participant')->values()->all())->toBe([
            [
                'name' => 'Alice Example',
                'email' => 'alice@example.com',
                'phone' => '+60123456789',
                'relationship_to_registrant' => 'spouse',
                'age' => 29,
                'gender' => 'female',
                'is_primary' => true,
            ],
        ])->and($capturedByTicketType->get($fallbackTicketType->id)?->pluck('participant')->values()->all())->toBe([
            [
                'name' => 'Maya Jones',
                'email' => 'maya@example.com',
                'phone' => '+60111222333',
                'is_primary' => true,
            ],
            [
                'name' => 'Maya Jones #2',
                'is_primary' => false,
            ],
        ]);
    });
});

it('prefers the customer email and phone columns when fallback participants are built', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $customer = Customer::create([
            'first_name' => 'Raw',
            'last_name' => 'Source',
            'email' => 'fresh@example.com',
            'phone' => '+60123456789',
            'is_guest' => false,
        ]);

        $customer->addContactMethod(ContactMethodData::email('stale@example.com'));
        $customer->addContactMethod(ContactMethodData::phone('+60987654321', countryCode: 'MY'));

        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
        ]);

        $ticketType = EventTicketType::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'price' => 1500,
        ]);

        $order = Order::factory()->create([
            'customer_type' => $customer->getMorphClass(),
            'customer_id' => $customer->id,
        ]);

        OrderItem::query()->create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'purchasable_type' => $ticketType->getMorphClass(),
            'purchasable_id' => $ticketType->id,
            'name' => $ticketType->name,
            'sku' => 'RAW-FALLBACK-' . Str::upper(Str::random(8)),
            'quantity' => 1,
            'unit_price' => $ticketType->price,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => $ticketType->currency,
        ]);

        $session = CheckoutSession::query()->create([
            'cart_id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'cart_snapshot' => [
                'items' => [
                    [
                        'id' => $ticketType->id,
                        'name' => $ticketType->name,
                        'price' => $ticketType->price,
                        'quantity' => 1,
                        'attributes' => [
                            'purchasable_id' => $ticketType->id,
                        ],
                        'associated_model' => [
                            'class' => EventTicketType::class,
                            'id' => $ticketType->id,
                        ],
                    ],
                ],
            ],
        ]);

        $captured = [];

        $registrationService = mock(RegistrationServiceInterface::class);
        $registrationService->shouldReceive('register')
            ->once()
            ->andReturnUsing(function (array $data) use (&$captured): EventRegistration {
                $captured[] = $data['participants'][0] ?? null;

                return new EventRegistration;
            });

        $step = app(CreateEventRegistrationsStep::class);
        $result = $step->handle($session->fresh());

        expect($result->isSuccessful())->toBeTrue()
            ->and($captured)->toHaveCount(1)
            ->and($captured[0])->toBe([
                'name' => 'Raw Source',
                'email' => 'fresh@example.com',
                'phone' => '+60123456789',
                'is_primary' => true,
            ]);
    });
});

it('routes registrations to the matching event scope for event, occurrence, and session ticket types', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
        ]);

        $eventTicketType = EventTicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 1500,
        ]);
        $occurrenceTicketType = EventTicketType::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'price' => 1500,
        ]);
        $sessionTicketType = EventTicketType::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'price' => 1500,
        ]);

        $order = Order::factory()->create();

        $eventOrderItem = OrderItem::query()->create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'purchasable_type' => $eventTicketType->getMorphClass(),
            'purchasable_id' => $eventTicketType->id,
            'name' => $eventTicketType->name,
            'sku' => 'EVENT-' . Str::upper(Str::random(8)),
            'quantity' => 1,
            'unit_price' => $eventTicketType->price,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => $eventTicketType->currency,
        ]);

        $occurrenceOrderItem = OrderItem::query()->create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'purchasable_type' => $occurrenceTicketType->getMorphClass(),
            'purchasable_id' => $occurrenceTicketType->id,
            'name' => $occurrenceTicketType->name,
            'sku' => 'OCC-' . Str::upper(Str::random(8)),
            'quantity' => 1,
            'unit_price' => $occurrenceTicketType->price,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => $occurrenceTicketType->currency,
        ]);

        $sessionOrderItem = OrderItem::query()->create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'purchasable_type' => $sessionTicketType->getMorphClass(),
            'purchasable_id' => $sessionTicketType->id,
            'name' => $sessionTicketType->name,
            'sku' => 'SES-' . Str::upper(Str::random(8)),
            'quantity' => 1,
            'unit_price' => $sessionTicketType->price,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => $sessionTicketType->currency,
        ]);

        $checkoutSession = CheckoutSession::query()->create([
            'cart_id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'cart_snapshot' => [
                'items' => [
                    [
                        'id' => $eventTicketType->id,
                        'name' => $eventTicketType->name,
                        'price' => $eventTicketType->price,
                        'quantity' => 1,
                        'attributes' => [
                            'purchasable_id' => $eventTicketType->id,
                            'participants' => [
                                [
                                    'name' => 'Event Scope',
                                    'is_primary' => true,
                                ],
                            ],
                        ],
                        'associated_model' => [
                            'class' => EventTicketType::class,
                            'id' => $eventTicketType->id,
                        ],
                    ],
                    [
                        'id' => $occurrenceTicketType->id,
                        'name' => $occurrenceTicketType->name,
                        'price' => $occurrenceTicketType->price,
                        'quantity' => 1,
                        'attributes' => [
                            'purchasable_id' => $occurrenceTicketType->id,
                            'participants' => [
                                [
                                    'name' => 'Occurrence Scope',
                                    'is_primary' => true,
                                ],
                            ],
                        ],
                        'associated_model' => [
                            'class' => EventTicketType::class,
                            'id' => $occurrenceTicketType->id,
                        ],
                    ],
                    [
                        'id' => $sessionTicketType->id,
                        'name' => $sessionTicketType->name,
                        'price' => $sessionTicketType->price,
                        'quantity' => 1,
                        'attributes' => [
                            'purchasable_id' => $sessionTicketType->id,
                            'participants' => [
                                [
                                    'name' => 'Session Scope',
                                    'is_primary' => true,
                                ],
                            ],
                        ],
                        'associated_model' => [
                            'class' => EventTicketType::class,
                            'id' => $sessionTicketType->id,
                        ],
                    ],
                ],
            ],
        ]);

        $captured = [];

        $registrationService = mock(RegistrationServiceInterface::class);
        $registrationService->shouldReceive('register')
            ->times(3)
            ->andReturnUsing(function (array $data) use (&$captured): EventRegistration {
                $captured[] = [
                    'ticket_type_id' => $data['items'][0]['event_ticket_type_id'] ?? null,
                    'event_id' => $data['event_id'] ?? null,
                    'event_occurrence_id' => $data['event_occurrence_id'] ?? null,
                    'event_session_id' => $data['event_session_id'] ?? null,
                    'participant_name' => $data['participants'][0]['name'] ?? null,
                ];

                return new EventRegistration;
            });

        $step = app(CreateEventRegistrationsStep::class);
        $result = $step->handle($checkoutSession->fresh());

        expect($result->isSuccessful())->toBeTrue();

        $capturedByTicketType = collect($captured)->keyBy('ticket_type_id');

        expect($capturedByTicketType->get($eventTicketType->id))->toMatchArray([
            'event_id' => $event->id,
            'event_occurrence_id' => null,
            'event_session_id' => null,
            'participant_name' => 'Event Scope',
        ]);

        expect($capturedByTicketType->get($occurrenceTicketType->id))->toMatchArray([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => null,
            'participant_name' => 'Occurrence Scope',
        ]);

        expect($capturedByTicketType->get($sessionTicketType->id))->toMatchArray([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'participant_name' => 'Session Scope',
        ]);

        expect($eventOrderItem->fresh()->exists)->toBeTrue()
            ->and($occurrenceOrderItem->fresh()->exists)->toBeTrue()
            ->and($sessionOrderItem->fresh()->exists)->toBeTrue();
    });
});
