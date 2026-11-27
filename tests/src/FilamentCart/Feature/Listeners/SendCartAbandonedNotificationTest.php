<?php

declare(strict_types=1);

use AIArmada\Cart\Events\CartAbandoned;
use AIArmada\Cart\Features\LiveCart\Models\CartModel;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot as Cart;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Pending;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\EntryPoints\Listeners\SendCartAbandonedNotification;
use AIArmada\FilamentCart\Notifications\CartAbandonedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('delivers abandoned cart notifications using the event owner context', function (): void {
    Notification::fake();

    config()->set('cart.owner.enabled', true);
    config()->set('cart.owner.include_global', false);
    config()->set('cart.owner.auto_assign_on_create', true);
    config()->set('checkout.owner.enabled', true);
    config()->set('checkout.owner.include_global', false);
    config()->set('checkout.owner.auto_assign_on_create', true);

    $owner = User::factory()->create();

    $event = OwnerContext::withOwner($owner, function (): CartAbandoned {
        $liveCart = CartModel::query()->create([
            'identifier' => 'abandoned-cart-1',
            'instance' => 'default',
            'items' => [
                'offer-1' => [
                    'id' => 'offer-1',
                    'name' => 'AI Awakening',
                    'price' => 1000,
                    'quantity' => 1,
                    'attributes' => [
                        'preferred_date' => 'March 1, 2026',
                    ],
                ],
            ],
            'conditions' => [],
            'metadata' => [],
            'version' => 1,
        ]);

        $cart = Cart::factory()->create([
            'identifier' => 'abandoned-cart-1',
            'instance' => 'default',
            'items' => [
                [
                    'id' => 'offer-1',
                    'name' => 'AI Awakening',
                    'price' => 1000,
                    'quantity' => 1,
                    'attributes' => [
                        'preferred_date' => 'March 1, 2026',
                    ],
                ],
            ],
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'MYR',
            'checkout_started_at' => now()->subHour(),
        ]);

        expect($cart->id)->not->toBe($liveCart->id);

        CheckoutSession::create([
            'cart_id' => $liveCart->id,
            'billing_data' => [
                'email' => 'purchaser@example.com',
            ],
            'payment_data' => [],
            'status' => Pending::class,
        ]);

        return CartAbandoned::fromSnapshot($cart->toSnapshotData());
    });

    OwnerContext::withOwner(null, function () use ($event): void {
        app(SendCartAbandonedNotification::class)->handle($event);
    });

    Notification::assertSentOnDemand(
        CartAbandonedNotification::class,
        function (CartAbandonedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $channels === ['mail'] && ($notifiable->routes['mail'] ?? null) === 'purchaser@example.com';
        },
    );
});

it('skips queued notifications for deleted owner tuples', function (): void {
    Notification::fake();

    config()->set('cart.owner.enabled', true);
    config()->set('cart.owner.include_global', false);
    config()->set('checkout.owner.enabled', true);

    $owner = User::factory()->create();

    $event = OwnerContext::withOwner($owner, function (): CartAbandoned {
        $cart = Cart::factory()->create([
            'identifier' => 'deleted-owner-cart',
            'instance' => 'default',
            'items' => [['id' => 'offer-1', 'name' => 'Offer', 'price' => 1000, 'quantity' => 1]],
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'MYR',
        ]);

        return CartAbandoned::fromSnapshot($cart->toSnapshotData());
    });

    $owner->delete();

    OwnerContext::withOwner(null, function () use ($event): void {
        app(SendCartAbandonedNotification::class)->handle($event);
    });

    Notification::assertNothingSent();
});

it('does not match checkout sessions keyed by the snapshot id instead of the live cart id', function (): void {
    Notification::fake();

    config()->set('cart.owner.enabled', false);
    config()->set('checkout.owner.enabled', false);

    $liveCart = CartModel::query()->create([
        'identifier' => 'snapshot-id-session-cart',
        'instance' => 'default',
        'items' => ['offer-1' => ['id' => 'offer-1', 'name' => 'Offer', 'price' => 1000, 'quantity' => 1]],
        'conditions' => [],
        'metadata' => [],
        'version' => 1,
    ]);

    $snapshot = Cart::factory()->create([
        'identifier' => 'snapshot-id-session-cart',
        'instance' => 'default',
        'items' => [['id' => 'offer-1', 'name' => 'Offer', 'price' => 1000, 'quantity' => 1]],
        'items_count' => 1,
        'quantity' => 1,
        'subtotal' => 1000,
        'total' => 1000,
        'currency' => 'MYR',
    ]);

    CheckoutSession::create([
        'cart_id' => $snapshot->id,
        'billing_data' => ['email' => 'snapshot-id@example.com'],
        'payment_data' => [],
        'status' => Pending::class,
    ]);

    app(SendCartAbandonedNotification::class)->handle(CartAbandoned::fromSnapshot($snapshot->toSnapshotData()));

    expect($liveCart->id)->not->toBe($snapshot->id);
    Notification::assertNothingSent();
});

it('preserves trusted global abandoned cart event semantics', function (): void {
    Notification::fake();

    config()->set('cart.owner.enabled', true);
    config()->set('cart.owner.include_global', false);
    config()->set('checkout.owner.enabled', true);
    config()->set('checkout.owner.include_global', false);

    $event = OwnerContext::withOwner(null, function (): CartAbandoned {
        $liveCart = CartModel::query()->create([
            'identifier' => 'global-abandoned-cart',
            'instance' => 'default',
            'items' => ['offer-1' => ['id' => 'offer-1', 'name' => 'Offer', 'price' => 1000, 'quantity' => 1]],
            'conditions' => [],
            'metadata' => [],
            'version' => 1,
        ]);

        $snapshot = Cart::factory()->create([
            'identifier' => 'global-abandoned-cart',
            'instance' => 'default',
            'items' => [['id' => 'offer-1', 'name' => 'Offer', 'price' => 1000, 'quantity' => 1]],
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'MYR',
        ]);

        CheckoutSession::create([
            'cart_id' => $liveCart->id,
            'billing_data' => ['email' => 'global@example.com'],
            'payment_data' => [],
            'status' => Pending::class,
        ]);

        return CartAbandoned::fromSnapshot($snapshot->toSnapshotData());
    });

    OwnerContext::withOwner(null, function () use ($event): void {
        app(SendCartAbandonedNotification::class)->handle($event);
    });

    Notification::assertSentOnDemand(
        CartAbandonedNotification::class,
        function (CartAbandonedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $channels === ['mail'] && ($notifiable->routes['mail'] ?? null) === 'global@example.com';
        },
    );
});
