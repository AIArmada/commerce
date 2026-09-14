<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Illuminate\Support\Facades\Route;

uses(CashierChipTestCase::class);

beforeEach(function (): void {
    $this->gateway = new ChipGateway;

    // Create a mock billable for testing
    $this->billable = Mockery::mock(BillableContract::class);
});

describe('customerPortalUrl', function (): void {
    it('falls back to the app URL for external return URLs', function (): void {
        $url = $this->gateway->customerPortalUrl($this->billable, 'https://example.com/dashboard');

        expect($url)->toBe(url('/'));
    });

    it('passes through relative and same-host return URLs', function (): void {
        expect($this->gateway->customerPortalUrl($this->billable, '/dashboard'))->toBe('/dashboard');

        $appUrl = (string) config('app.url');

        expect($this->gateway->customerPortalUrl($this->billable, $appUrl . '/dashboard'))
            ->toBe($appUrl . '/dashboard');
    });

    it('returns billing panel route when it exists', function (): void {
        // Register a fake route for testing - need to use getRoutes to check
        $router = app('router');
        $router->get('/billing', fn () => 'billing')
            ->name('filament.billing.pages.dashboard');

        // Refresh the route collection
        $router->getRoutes()->refreshNameLookups();

        $returnUrl = 'https://example.com/dashboard';

        $url = $this->gateway->customerPortalUrl($this->billable, $returnUrl);

        expect($url)->toContain('billing');
    });

    it('rejects panels outside the allow-list', function (): void {
        // Register a custom panel route
        $router = app('router');
        $router->get('/customer-portal', fn () => 'portal')
            ->name('filament.customer.pages.dashboard');

        // Refresh the route collection
        $router->getRoutes()->refreshNameLookups();

        $url = $this->gateway->customerPortalUrl($this->billable, '/dashboard', [
            'panel' => 'customer',
        ]);

        expect($url)->toBe('/dashboard');
    });

    it('uses an allow-listed custom panel id from options', function (): void {
        config()->set('cashier.portal.allowed_panels', ['billing', 'customer']);

        // Register a custom panel route
        $router = app('router');
        $router->get('/customer-portal', fn () => 'portal')
            ->name('filament.customer.pages.dashboard');

        // Refresh the route collection
        $router->getRoutes()->refreshNameLookups();

        $url = $this->gateway->customerPortalUrl($this->billable, '/dashboard', [
            'panel' => 'customer',
        ]);

        expect($url)->toContain('customer-portal');
    });
});

describe('gateway name', function (): void {
    it('returns chip as gateway name', function (): void {
        expect($this->gateway->name())->toBe('chip');
    });
});
