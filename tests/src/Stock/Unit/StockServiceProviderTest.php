<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Stock\Cart\CartManagerWithStock;
use AIArmada\Stock\Services\StockReservationService;
use AIArmada\Stock\Services\StockService;
use AIArmada\Stock\StockServiceProvider;
use Illuminate\Support\Facades\Event;

test('provides correct bindings', function (): void {
    $provider = new StockServiceProvider(app());

    expect($provider->provides())->toBe([
        StockService::class,
        StockReservationService::class,
        'stock',
        'stock.reservations',
    ]);
});

// Skipping full container test as it requires full app boot

test('registers cart integration when enabled', function (): void {
    config(['stock.cart.enabled' => true]);

    $this->app->register(StockServiceProvider::class);
    $this->app->boot(StockServiceProvider::class);

    $cartManager = resolve(CartManagerInterface::class);

    // The cart manager can be wrapped by multiple decorators (Stock, Affiliates, Vouchers, etc.)
    // Check if CartManagerWithStock is in the decorator chain
    $found = false;
    $current = $cartManager;
    while ($current !== null) {
        if ($current instanceof CartManagerWithStock) {
            $found = true;
            break;
        }
        if (method_exists($current, 'getBaseManager')) {
            $next = $current->getBaseManager();
            if ($next === $current) {
                break; // Prevent infinite loop
            }
            $current = $next;
        } else {
            break;
        }
    }

    expect($found)->toBeTrue();
});

test('skips cart integration when disabled', function (): void {
    config(['stock.cart.enabled' => false]);

    $this->app->register(StockServiceProvider::class);
    $this->app->boot(StockServiceProvider::class);

    $cartManager = resolve(CartManagerInterface::class);

    expect($cartManager)->not->toBeInstanceOf(CartManagerWithStock::class);
});

test('registers payment integration when enabled', function (): void {
    config(['stock.payment.auto_deduct' => true]);

    $this->app->register(StockServiceProvider::class);
    $this->app->boot(StockServiceProvider::class);

    $events = [
        AIArmada\Cashier\Events\PaymentSucceeded::class,
        AIArmada\CashierChip\Events\PaymentSucceeded::class,
    ];

    $checked = 0;
    foreach ($events as $event) {
        if (class_exists($event)) {
            expect(Event::hasListeners($event))->toBeTrue();
            $checked++;
        }
    }

    if ($checked === 0) {
        $this->markTestSkipped('No payment events found to check.');
    }
});

test('registers command', function (): void {
    $this->app->register(StockServiceProvider::class);

    // Check that the command class is available via Artisan
    $commands = Artisan::all();
    expect(array_key_exists('stock:cleanup-reservations', $commands))->toBeTrue();
});
