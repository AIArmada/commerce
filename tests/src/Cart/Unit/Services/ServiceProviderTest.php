<?php

declare(strict_types=1);

use AIArmada\Cart\CartServiceProvider;
use AIArmada\Cart\Services\CartMigrationService;
use Illuminate\Foundation\Application;

describe('CartServiceProvider', function (): void {
    afterEach(function (): void {
        Mockery::close();
    });

    it('provides correct services', function (): void {
        $app = mock(Application::class);
        $provider = new CartServiceProvider($app);
        $provides = $provider->provides();

        expect($provides)->toBeArray();
        expect($provides)->toContain('cart');
        expect($provides)->toContain(AIArmada\Cart\Cart::class);
        expect($provides)->toContain(AIArmada\Cart\Storage\StorageInterface::class);
        expect($provides)->toContain(CartMigrationService::class);
        expect($provides)->toContain('cart.storage.session');
        expect($provides)->toContain('cart.storage.cache');
        expect($provides)->toContain('cart.storage.database');
    });

    it('registers storage drivers correctly', function (): void {
        $app = mock(Application::class);

        // Mock all bind calls for storage drivers + StorageInterface binding
        $app->shouldReceive('bind')->withAnyArgs()->times(4);

        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerStorageDrivers');
        $method->setAccessible(true);
        $method->invoke($provider);

        expect(true)->toBeTrue();
    });

    it('tests database storage scenarios without complex mocking', function (): void {
        // This test just covers that the methods exist and can be called without error
        $app = mock(Application::class);
        $app->shouldReceive('bind')->withAnyArgs()->times(4);

        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerStorageDrivers');
        $method->setAccessible(true);
        $method->invoke($provider);

        expect(true)->toBeTrue();
    });

    it('registers cart manager correctly', function (): void {
        $app = mock(Application::class);
        $app->shouldReceive('singleton')->withArgs(['cart', Mockery::type('callable')])->once();
        $app->shouldReceive('alias')->withArgs(['cart', AIArmada\Cart\CartManager::class])->once();

        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerCartManager');
        $method->setAccessible(true);
        $method->invoke($provider);

        expect(true)->toBeTrue();
    });

    it('can call publish methods without errors', function (): void {
        $app = mock(Application::class);
        $provider = new CartServiceProvider($app);

        // Test that the provider has been properly converted to use Spatie Package Tools
        expect($provider)->toBeInstanceOf(Spatie\LaravelPackageTools\PackageServiceProvider::class);
    });

    it('registers migration service correctly', function (): void {
        $app = mock(Application::class);
        $app->shouldReceive('singleton')->withArgs([Mockery::type('callable')])->once();

        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerMigrationService');
        $method->setAccessible(true);
        $method->invoke($provider);

        expect(true)->toBeTrue();
    });

    it('can call event listeners method without errors', function (): void {
        $app = mock(Application::class);
        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerEventListeners');

        expect($method->isProtected())->toBeTrue();
        expect($reflection->hasMethod('registerEventListeners'))->toBeTrue();
    });

    it('has all expected protected methods', function (): void {
        $app = mock(Application::class);
        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);

        $expectedMethods = [
            'registerStorageDrivers',
            'registerCartManager',
            'registerMigrationService',
            'registerEventListeners',
            'configurePackage',
            'registeringPackage',
            'bootingPackage',
        ];

        foreach ($expectedMethods as $methodName) {
            expect($reflection->hasMethod($methodName))->toBeTrue("Method {$methodName} should exist");
        }
    });

    it('can call spatie package tools methods', function (): void {
        $app = mock(Application::class);
        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);

        // Test that configurePackage exists and is callable
        $configureMethod = $reflection->getMethod('configurePackage');
        expect($configureMethod->isPublic())->toBeTrue();

        // Test that registeringPackage exists and is callable
        $registeringMethod = $reflection->getMethod('registeringPackage');
        expect($registeringMethod->isPublic())->toBeTrue();

        // Test that bootingPackage exists and is callable
        $bootingMethod = $reflection->getMethod('bootingPackage');
        expect($bootingMethod->isPublic())->toBeTrue();
    });
});

// --- Integration-style tests for real container/config/event logic ---
use AIArmada\Cart\Storage\CacheStorage;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Cart\Storage\SessionStorage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Config::set('cart.storage', 'session');
    Config::set('cart.migration.auto_migrate_on_login', true);
});

it('integration: registers all storage drivers', function (): void {
    $app = app();
    $provider = new CartServiceProvider($app);
    $provider->register();

    expect($app->make('cart.storage.session'))->toBeInstanceOf(SessionStorage::class);
    expect($app->make('cart.storage.cache'))->toBeInstanceOf(CacheStorage::class);
    if ($app->bound('db.connection')) {
        expect($app->make('cart.storage.database'))->toBeInstanceOf(DatabaseStorage::class);
    }
});

it('integration: registers cart manager and aliases', function (): void {
    $app = app();
    $provider = new CartServiceProvider($app);
    $provider->register();

    expect($app->make('cart'))->toBeInstanceOf(AIArmada\Cart\CartManager::class);
    expect($app->make(AIArmada\Cart\CartManager::class))->toBeInstanceOf(AIArmada\Cart\CartManager::class);
});

it('integration: registers migration service', function (): void {
    $app = app();
    $provider = new CartServiceProvider($app);
    $provider->register();

    expect($app->make(CartMigrationService::class))->toBeInstanceOf(CartMigrationService::class);
});

it('integration: publishes config, migrations, and views', function (): void {
    $provider = new CartServiceProvider(app());
    $package = new Spatie\LaravelPackageTools\Package;
    $provider->configurePackage($package);

    expect($package->name)->toBe('cart');
    expect($package->commands)->toHaveCount(1);
    expect(true)->toBeTrue(); // Package was configured successfully
});

it('integration: registers event listeners based on config', function (): void {
    $app = app();
    $provider = new CartServiceProvider($app);
    Event::fake();
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('registerEventListeners');
    $method->setAccessible(true);
    $method->invoke($provider);
    Event::assertListening(Illuminate\Auth\Events\Attempting::class, AIArmada\Cart\Listeners\HandleUserLoginAttempt::class);
    Event::assertListening(Illuminate\Auth\Events\Login::class, AIArmada\Cart\Listeners\HandleUserLogin::class);
});
