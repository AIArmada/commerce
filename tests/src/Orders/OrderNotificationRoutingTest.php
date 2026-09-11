<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use Illuminate\Notifications\Notification;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
});

it('routes mail notifications to the billing address', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $order = Order::factory()->create();

        $address = Address::create([
            'line1' => '123 Billing Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
            'metadata' => [
                Order::ADDRESS_CONTACT_METADATA_KEY => [
                    'first_name' => 'Billing',
                    'last_name' => 'Customer',
                    'email' => 'billing@example.com',
                ],
            ],
        ]);
        $order->attachAddress($address, type: 'billing', isPrimary: true);

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

        $address = Address::create([
            'line1' => '456 Shipping Road',
            'city' => 'Johor Bahru',
            'postcode' => '80000',
            'country_code' => 'MY',
            'metadata' => [
                Order::ADDRESS_CONTACT_METADATA_KEY => [
                    'first_name' => 'Shipping',
                    'last_name' => 'Customer',
                    'email' => 'shipping@example.com',
                ],
            ],
        ]);
        $order->attachAddress($address, type: 'shipping', isPrimary: true);

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
