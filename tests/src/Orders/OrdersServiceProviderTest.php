<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Listeners\CreateInvoiceForPaidOrder;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Notifications\PaymentConfirmationNotification;
use AIArmada\Orders\OrdersServiceProvider;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

describe('OrdersServiceProvider', function (): void {
    it('can be instantiated', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect($provider)->toBeInstanceOf(OrdersServiceProvider::class);
    });

    it('has register method', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(method_exists($provider, 'register'))->toBeTrue();
    });

    it('has boot method', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(method_exists($provider, 'boot'))->toBeTrue();
    });

    it('has registerPolicies method', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(method_exists($provider, 'registerPolicies'))->toBeTrue();
    });

    it('has registerEventListeners method', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(method_exists($provider, 'registerEventListeners'))->toBeTrue();
    });

    it('can call register method without errors', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(fn () => $provider->register())->not->toThrow(Exception::class);
    });

    it('can call boot method without errors', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(fn () => $provider->boot())->not->toThrow(Exception::class);
    });

    it('does not register event listeners when docs and payment notifications are disabled', function (): void {
        config()->set('orders.integrations.docs.enabled', false);
        config()->set('orders.notifications.payment_confirmation.enabled', false);

        Event::shouldReceive('listen')->never();

        $provider = new OrdersServiceProvider(app());

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerEventListeners');
        $method->invoke($provider);
    });

    it('registers the payment confirmation listener when enabled', function (): void {
        config()->set('orders.integrations.docs.enabled', false);
        config()->set('orders.notifications.payment_confirmation.enabled', true);

        Event::shouldReceive('listen')
            ->once()
            ->with(OrderPaid::class, Mockery::type(Closure::class));

        $provider = new OrdersServiceProvider(app());

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerEventListeners');
        $method->invoke($provider);
    });

    it('registers the docs and payment confirmation listeners when the integration is explicitly enabled', function (): void {
        config()->set('orders.integrations.docs.enabled', true);
        config()->set('orders.notifications.payment_confirmation.enabled', true);

        Event::shouldReceive('listen')
            ->once()
            ->with(OrderPaid::class, CreateInvoiceForPaidOrder::class);

        Event::shouldReceive('listen')
            ->once()
            ->with(OrderPaid::class, Mockery::type(Closure::class));

        $provider = new OrdersServiceProvider(app());

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerEventListeners');
        $method->invoke($provider);
    });

    it('sends payment confirmation notifications on demand', function (): void {
        Notification::fake();

        config()->set('orders.owner.enabled', true);
        config()->set('orders.owner.include_global', false);
        config()->set('orders.owner.auto_assign_on_create', true);
        config()->set('orders.integrations.docs.enabled', false);
        config()->set('orders.notifications.payment_confirmation.enabled', true);

        $listener = null;

        Event::shouldReceive('listen')
            ->once()
            ->with(OrderPaid::class, Mockery::on(function (Closure $closure) use (&$listener): bool {
                $listener = $closure;

                return true;
            }));

        Event::shouldReceive('dispatch')
            ->zeroOrMoreTimes()
            ->andReturnNull();

        $provider = new OrdersServiceProvider(app());

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerEventListeners');
        $method->invoke($provider);

        $owner = User::factory()->create();

        $order = OwnerContext::withOwner($owner, function (): Order {
            $order = Order::factory()
                ->paid()
                ->create();

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

            return $order;
        });

        OwnerContext::withOwner($owner, function () use (&$listener, $order): void {
            expect($listener)->toBeInstanceOf(Closure::class);

            $listener(new OrderPaid($order, 'txn_123', 'chip'));
        });

        Notification::assertSentOnDemand(
            PaymentConfirmationNotification::class,
            function (PaymentConfirmationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
                return $channels === ['mail'] && ($notifiable->routes['mail'] ?? null) === [
                    'billing@example.com' => 'Billing Customer',
                ];
            },
        );
    });
});
