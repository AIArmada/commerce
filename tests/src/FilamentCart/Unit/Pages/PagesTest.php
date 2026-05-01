<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Pages\CartDashboard;
use AIArmada\FilamentCart\Pages\LiveDashboardPage;
use Filament\Pages\Page;

describe('Pages Instantiation', function (): void {
    it('can instantiate CartDashboard', function (): void {
        $page = new CartDashboard;
        expect($page)->toBeInstanceOf(Page::class);
    });

    it('can instantiate LiveDashboardPage', function (): void {
        $page = new LiveDashboardPage;
        expect($page)->toBeInstanceOf(Page::class);
    });

    it('uses configured navigation group for cart dashboard', function (): void {
        config(['filament-cart.navigation_group' => 'Operations']);

        expect(CartDashboard::getNavigationGroup())->toBe('Operations');
    });
});
