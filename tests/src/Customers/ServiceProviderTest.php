<?php

declare(strict_types=1);

use AIArmada\Customers\CustomersServiceProvider;
use Illuminate\Support\ServiceProvider;

describe('CustomersServiceProvider', function (): void {
    describe('Instantiation', function (): void {
        it('can be instantiated', function (): void {
            $provider = new CustomersServiceProvider(app());

            expect($provider)->toBeInstanceOf(ServiceProvider::class);
        });
    });

    describe('register Method', function (): void {
        it('merges config', function (): void {
            // Config should be merged when provider is registered
            $provider = new CustomersServiceProvider(app());
            $provider->register();

            // Check that config is available
            expect(config('customers'))->toBeArray();
        });
    });

    describe('boot Method', function (): void {
        it('can boot without errors', function (): void {
            $provider = new CustomersServiceProvider(app());
            $provider->register();
            $provider->boot();

            // If we get here without exception, boot worked
            expect(true)->toBeTrue();
        });

        it('loads translations', function (): void {
            $provider = new CustomersServiceProvider(app());
            $provider->register();
            $provider->boot();

            // Verify translations namespace is registered
            $translator = app('translator');
            $namespaces = $translator->getLoader()->namespaces();

            expect($namespaces)->toHaveKey('customers');
        });
    });

    describe('Publishing', function (): void {
        it('has config publish paths defined', function (): void {
            $provider = new CustomersServiceProvider(app());
            $provider->register();
            $provider->boot();

            // Get all publishable paths
            $paths = ServiceProvider::pathsToPublish(CustomersServiceProvider::class, 'customers-config');

            expect($paths)->toBeArray()
                ->and(count($paths))->toBeGreaterThanOrEqual(1);
        });

        it('has migrations publish paths defined', function (): void {
            $provider = new CustomersServiceProvider(app());
            $provider->register();
            $provider->boot();

            $paths = ServiceProvider::pathsToPublish(CustomersServiceProvider::class, 'customers-migrations');

            expect($paths)->toBeArray()
                ->and(count($paths))->toBeGreaterThanOrEqual(1);
        });
    });
});
