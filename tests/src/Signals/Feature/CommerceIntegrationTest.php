<?php

declare(strict_types=1);

use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Events\CheckoutStarted;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Models\Order;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(SignalsTestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('orders');
    Schema::create('orders', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('order_number')->unique();
        $table->string('status', 50)->default('created')->index();
        $table->nullableUuidMorphs('customer');
        $table->nullableUuidMorphs('owner');
        $table->unsignedBigInteger('subtotal')->default(0);
        $table->unsignedBigInteger('discount_total')->default(0);
        $table->unsignedBigInteger('shipping_total')->default(0);
        $table->unsignedBigInteger('tax_total')->default(0);
        $table->unsignedBigInteger('grand_total')->default(0);
        $table->string('currency', 3)->default('MYR');
        $table->text('notes')->nullable();
        $table->text('internal_notes')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamp('paid_at')->nullable()->index();
        $table->timestamp('shipped_at')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('canceled_at')->nullable();
        $table->string('cancellation_reason')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('checkout_sessions');
    Schema::create('checkout_sessions', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('cart_id')->index();
        $table->foreignUuid('customer_id')->nullable()->index();
        $table->foreignUuid('order_id')->nullable()->index();
        $table->string('payment_id')->nullable()->index();
        $table->nullableUuidMorphs('owner');
        $table->string('status')->default('pending')->index();
        $table->string('current_step')->nullable();
        $table->string('error_message')->nullable();
        $table->json('cart_snapshot')->nullable();
        $table->json('step_states')->nullable();
        $table->json('shipping_data')->nullable();
        $table->json('billing_data')->nullable();
        $table->json('pricing_data')->nullable();
        $table->json('discount_data')->nullable();
        $table->json('tax_data')->nullable();
        $table->json('payment_data')->nullable();
        $table->string('payment_redirect_url', 2048)->nullable();
        $table->unsignedSmallInteger('payment_attempts')->default(0);
        $table->string('selected_shipping_method')->nullable();
        $table->string('selected_payment_gateway')->nullable();
        $table->unsignedBigInteger('subtotal')->default(0);
        $table->unsignedBigInteger('discount_total')->default(0);
        $table->unsignedBigInteger('shipping_total')->default(0);
        $table->unsignedBigInteger('tax_total')->default(0);
        $table->unsignedBigInteger('grand_total')->default(0);
        $table->string('currency', 3)->default('MYR');
        $table->timestamp('expires_at')->nullable()->index();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
});

it('records a checkout completed signal for the matching owner property', function (): void {
    $owner = User::query()->firstOrFail();
    $otherOwner = User::query()->create([
        'name' => 'Other Owner',
        'email' => 'other-owner@example.com',
        'password' => 'secret',
    ]);

    $property = TrackedProperty::query()->create([
        'name' => 'Owner A Storefront',
        'slug' => 'owner-a-storefront',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    OwnerContext::withOwner($otherOwner, static function (): void {
        TrackedProperty::query()->create([
            'name' => 'Owner B Storefront',
            'slug' => 'owner-b-storefront',
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    });

    $session = CheckoutSession::query()->create([
        'cart_id' => 'cart-123',
        'customer_id' => $owner->id,
        'status' => 'completed',
        'selected_payment_gateway' => 'chip',
        'grand_total' => 15900,
        'currency' => 'MYR',
        'completed_at' => now(),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    Event::dispatch(new CheckoutCompleted($session));

    $event = SignalEvent::query()->withoutOwnerScope()->sole();

    expect($event->tracked_property_id)->toBe($property->id)
        ->and($event->event_name)->toBe('checkout.completed')
        ->and($event->event_category)->toBe('checkout')
        ->and($event->signal_session_id)->toBeNull()
        ->and($event->signal_identity_id)->not->toBeNull()
        ->and($event->revenue_minor)->toBe(15900)
        ->and($event->properties)->toMatchArray([
            'checkout_session_id' => $session->id,
            'payment_gateway' => 'chip',
        ]);
});

it('records a checkout started signal for the matching owner property', function (): void {
    $owner = User::query()->firstOrFail();
    $otherOwner = User::query()->create([
        'name' => 'Started Other Owner',
        'email' => 'started-other-owner@example.com',
        'password' => 'secret',
    ]);

    $property = TrackedProperty::query()->create([
        'name' => 'Owner A Checkout Funnel',
        'slug' => 'owner-a-checkout-funnel',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    OwnerContext::withOwner($otherOwner, static function (): void {
        TrackedProperty::query()->create([
            'name' => 'Owner B Checkout Funnel',
            'slug' => 'owner-b-checkout-funnel',
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    });

    $session = CheckoutSession::query()->create([
        'cart_id' => 'cart-start-123',
        'customer_id' => $owner->id,
        'status' => 'pending',
        'selected_shipping_method' => 'standard',
        'selected_payment_gateway' => 'chip',
        'grand_total' => 9900,
        'currency' => 'MYR',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    Event::dispatch(new CheckoutStarted($session));

    $event = SignalEvent::query()->withoutOwnerScope()->sole();

    expect($event->tracked_property_id)->toBe($property->id)
        ->and($event->event_name)->toBe('checkout.started')
        ->and($event->event_category)->toBe('checkout')
        ->and($event->signal_session_id)->toBeNull()
        ->and($event->signal_identity_id)->not->toBeNull()
        ->and($event->revenue_minor)->toBe(9900)
        ->and($event->properties)->toMatchArray([
            'checkout_session_id' => $session->id,
            'payment_gateway' => 'chip',
            'shipping_method' => 'standard',
        ]);
});

it('records an order paid signal as a conversion for the matching owner property', function (): void {
    $owner = User::query()->firstOrFail();
    $otherOwner = User::query()->create([
        'name' => 'Another Owner',
        'email' => 'another-owner@example.com',
        'password' => 'secret',
    ]);

    $property = TrackedProperty::query()->create([
        'name' => 'Owner A Orders',
        'slug' => 'owner-a-orders',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    OwnerContext::withOwner($otherOwner, static function (): void {
        TrackedProperty::query()->create([
            'name' => 'Owner B Orders',
            'slug' => 'owner-b-orders',
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    });

    $order = Order::query()->create([
        'order_number' => 'ORD-' . Str::upper(Str::random(8)),
        'customer_id' => $owner->id,
        'customer_type' => $owner->getMorphClass(),
        'grand_total' => 24900,
        'currency' => 'MYR',
        'paid_at' => now(),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    Event::dispatch(new OrderPaid($order, 'txn_1001', 'chip'));

    $event = SignalEvent::query()->withoutOwnerScope()->sole();

    expect($event->tracked_property_id)->toBe($property->id)
        ->and($event->event_name)->toBe('order.paid')
        ->and($event->event_category)->toBe('conversion')
        ->and($event->signal_session_id)->toBeNull()
        ->and($event->signal_identity_id)->not->toBeNull()
        ->and($event->revenue_minor)->toBe(24900)
        ->and($event->properties)->toMatchArray([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'gateway' => 'chip',
            'transaction_id' => 'txn_1001',
        ]);
});
