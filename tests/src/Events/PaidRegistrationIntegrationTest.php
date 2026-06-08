<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Actions\StartOccurrenceCheckoutAction;
use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Orders\Events\OrderCanceled;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderRefunded;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Created;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('checkout.owner.enabled', false);
    config()->set('orders.owner.enabled', false);
    config()->set('orders.integrations.docs.enabled', false);

    Cart::setInstance('public-checkout');
    Cart::setIdentifier('events-paid-registration-' . uniqid());
    Cart::clear();
    Cart::clearConditions();
    Cart::clearMetadata();
});

it('starts checkout for a paid occurrence backed by a product sellable', function (): void {
    $product = Product::create([
        'name' => 'Paid Product Seat',
        'slug' => 'paid-product-seat-' . uniqid(),
        'price' => 12000,
        'status' => ProductStatus::Active,
    ]);

    $event = EventModel::create([
        'name' => 'Paid Product Event',
        'slug' => 'paid-product-event-' . uniqid(),
        'status' => EventStatus::Active,

        'registration_required' => true,
        'default_timezone' => 'UTC',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'product_id' => $product->id,
        'registration_mode' => 'paid',
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now('UTC')->addDay(),
        'timezone' => 'UTC',
    ]);

    expect($occurrence->isPaidRegistration())->toBeTrue();

    $intent = app(EventCheckoutIntentResolver::class)->resolve($occurrence, 2, ['ticket_tier' => 'standard']);

    expect($intent)->not->toBeNull();

    $session = app(StartOccurrenceCheckoutAction::class)->handle(
        $occurrence,
        2,
        ['ticket_tier' => 'standard'],
    );

    $cartItem = array_values((array) data_get($session->cart_snapshot, 'items', []))[0] ?? null;

    expect($session)->toBeInstanceOf(CheckoutSession::class)
        ->and($cartItem)->not->toBeNull()
        ->and(data_get($cartItem, 'name'))->toBe('Paid Product Seat')
        ->and(data_get($cartItem, 'price'))->toBe(12000)
        ->and(data_get($cartItem, 'attributes.product_id'))->toBe($product->id)
        ->and(data_get($cartItem, 'attributes.checkout_metadata.ticket_tier'))->toBe('standard')
        ->and(data_get($session->cart_snapshot, 'metadata.event_id'))->toBe($event->id)
        ->and(data_get($session->cart_snapshot, 'metadata.occurrence_id'))->toBe($occurrence->id);
});

it('starts checkout for a paid occurrence backed by a variant sellable and keeps the parent product id', function (): void {
    $product = Product::create([
        'name' => 'Paid Variant Product',
        'slug' => 'paid-variant-product-' . uniqid(),
        'price' => 10000,
        'status' => ProductStatus::Active,
    ]);

    $variant = Variant::create([
        'product_id' => $product->id,
        'name' => 'VIP Access',
        'sku' => 'VIP-' . uniqid(),
        'price' => 15000,
        'is_enabled' => true,
    ]);

    $event = EventModel::create([
        'name' => 'Paid Variant Event',
        'slug' => 'paid-variant-event-' . uniqid(),
        'status' => EventStatus::Active,

        'registration_required' => true,
        'default_timezone' => 'UTC',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'variant_id' => $variant->id,
        'registration_mode' => 'paid',
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now('UTC')->addDays(2),
        'timezone' => 'UTC',
    ]);

    expect($occurrence->isPaidRegistration())->toBeTrue();

    $intent = app(EventCheckoutIntentResolver::class)->resolve($occurrence, 1, ['ticket_tier' => 'vip']);

    expect($intent)->not->toBeNull();

    $session = app(StartOccurrenceCheckoutAction::class)->handle(
        $occurrence,
        1,
        ['ticket_tier' => 'vip'],
    );

    $cartItem = array_values((array) data_get($session->cart_snapshot, 'items', []))[0] ?? null;

    expect($session)->toBeInstanceOf(CheckoutSession::class)
        ->and($cartItem)->not->toBeNull()
        ->and(data_get($cartItem, 'price'))->toBe(15000)
        ->and(data_get($cartItem, 'attributes.product_id'))->toBe($product->id)
        ->and(data_get($cartItem, 'attributes.variant_id'))->toBe($variant->id)
        ->and(data_get($cartItem, 'attributes.checkout_metadata.ticket_tier'))->toBe('vip')
        ->and(data_get($session->cart_snapshot, 'metadata.occurrence_id'))->toBe($occurrence->id);
});

it('fulfills paid event registrations and syncs cancellations and refunds back to them', function (): void {
    Event::fakeExcept([
        OrderPaid::class,
        OrderCanceled::class,
        OrderRefunded::class,
    ]);

    expect(app(Dispatcher::class)->hasListeners(OrderPaid::class))->toBeTrue()
        ->and(app(Dispatcher::class)->hasListeners(OrderCanceled::class))->toBeTrue()
        ->and(app(Dispatcher::class)->hasListeners(OrderRefunded::class))->toBeTrue();

    $customer = Customer::create([
        'first_name' => 'Paid',
        'last_name' => 'Customer',
        'email' => 'paid-customer@example.com',
    ]);

    $event = EventModel::create([
        'name' => 'Paid Fulfillment Event',
        'slug' => 'paid-fulfillment-event-' . uniqid(),
        'status' => EventStatus::Active,

        'registration_required' => true,
        'default_timezone' => 'UTC',
    ]);

    $occurrence = Occurrence::create([
        'event_id' => $event->id,
        'registration_mode' => 'paid',
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now('UTC')->addDays(3),
        'timezone' => 'UTC',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-EVT-' . uniqid(),
        'customer_id' => $customer->id,
        'customer_type' => $customer->getMorphClass(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 9700,
        'grand_total' => 9700,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'name' => 'Paid Event Seat',
        'sku' => 'paid-event-seat',
        'quantity' => 1,
        'unit_price' => 9700,
        'total' => 9700,
        'options' => [
            'checkout_metadata' => [
                'occurrence_id' => $occurrence->id,
            ],
        ],
    ]);

    Event::dispatch(new OrderPaid($order->fresh(['items', 'customer']) ?? $order, 'txn_paid_event_123', 'chip'));

    $registration = Registration::query()
        ->where('order_id', $order->id)
        ->first();

    expect($registration)->not->toBeNull()
        ->and($registration?->status)->toBe(RegistrationStatus::Confirmed)
        ->and($registration?->order_id)->toBe($order->id)
        ->and($registration?->order_item_id)->toBe($orderItem->id)
        ->and($registration?->purchaser_customer_id)->toBe($customer->id)
        ->and($registration?->email)->toBe('paid-customer@example.com');

    Event::dispatch(new OrderCanceled($order->fresh() ?? $order, 'Customer changed mind'));

    $registration->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Cancelled)
        ->and(data_get($registration->metadata, 'cancellation_reason'))->toBe('Customer changed mind');

    Event::dispatch(new OrderRefunded($order->fresh() ?? $order, 9700, 'Customer changed mind'));

    $registration->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Refunded)
        ->and(data_get($registration->metadata, 'refund_reason'))->toBe('Customer changed mind')
        ->and(data_get($registration->metadata, 'refund_context.source'))->toBe('order_refunded');
});
