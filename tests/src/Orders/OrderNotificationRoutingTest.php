<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use Illuminate\Notifications\Notification;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
});

it('routes mail notifications to the billing address', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $order = Order::factory()->create();

        $order->addresses()->create([
            'type' => 'billing',
            'first_name' => 'Billing',
            'last_name' => 'Customer',
            'line1' => '123 Billing Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
            'email' => 'billing@example.com',
        ]);

        $notification = new class extends Notification
        {
            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                return ['mail'];
            }
        };

        expect($order->routeNotificationForMail($notification))->toBe([
            'billing@example.com' => 'Billing Customer',
        ]);
    });
});

it('falls back to the shipping address when billing is missing', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $order = Order::factory()->create();

        $order->addresses()->create([
            'type' => 'shipping',
            'first_name' => 'Shipping',
            'last_name' => 'Customer',
            'line1' => '456 Shipping Road',
            'city' => 'Johor Bahru',
            'postcode' => '80000',
            'country_code' => 'MY',
            'email' => 'shipping@example.com',
        ]);

        $notification = new class extends Notification
        {
            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                return ['mail'];
            }
        };

        expect($order->routeNotificationForMail($notification))->toBe([
            'shipping@example.com' => 'Shipping Customer',
        ]);
    });
});
