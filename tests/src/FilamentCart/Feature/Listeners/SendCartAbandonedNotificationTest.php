<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Pending;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Events\CartAbandoned;
use AIArmada\FilamentCart\Listeners\SendCartAbandonedNotification;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Notifications\CartAbandonedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('delivers abandoned cart notifications using the event owner context', function (): void {
    Notification::fake();

    config()->set('filament-cart.owner.enabled', true);
    config()->set('filament-cart.owner.include_global', false);
    config()->set('filament-cart.owner.auto_assign_on_create', true);
    config()->set('checkout.owner.enabled', true);
    config()->set('checkout.owner.include_global', false);
    config()->set('checkout.owner.auto_assign_on_create', true);

    $owner = User::factory()->create();

    $event = OwnerContext::withOwner($owner, function (): CartAbandoned {
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

        CheckoutSession::create([
            'cart_id' => $cart->id,
            'billing_data' => [
                'email' => 'purchaser@example.com',
            ],
            'payment_data' => [],
            'status' => Pending::class,
        ]);

        return CartAbandoned::fromCart($cart);
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
