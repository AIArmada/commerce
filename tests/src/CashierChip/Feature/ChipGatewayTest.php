<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Illuminate\Support\Facades\Route;

uses(CashierChipTestCase::class);

beforeEach(function () {
    $this->gateway = new ChipGateway();
    
    // Create a mock billable for testing
    $this->billable = Mockery::mock(BillableContract::class);
});

describe('customerPortalUrl', function () {
    it('returns return URL when billing route does not exist', function () {
        $returnUrl = 'https://example.com/dashboard';
        
        $url = $this->gateway->customerPortalUrl($this->billable, $returnUrl);
        
        expect($url)->toBe($returnUrl);
    });

    it('returns billing panel route when it exists', function () {
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

    it('uses custom panel id from options', function () {
        // Register a custom panel route
        $router = app('router');
        $router->get('/customer-portal', fn () => 'portal')
            ->name('filament.customer.pages.dashboard');
        
        // Refresh the route collection
        $router->getRoutes()->refreshNameLookups();
        
        $returnUrl = 'https://example.com/dashboard';
        
        $url = $this->gateway->customerPortalUrl($this->billable, $returnUrl, [
            'panel' => 'customer',
        ]);
        
        expect($url)->toContain('customer-portal');
    });
});

describe('gateway name', function () {
    it('returns chip as gateway name', function () {
        expect($this->gateway->name())->toBe('chip');
    });
});
