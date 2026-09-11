<?php

declare(strict_types=1);

use AIArmada\Cart\Snapshots\CartSnapshot as Cart;
use AIArmada\FilamentCart\Pages\CartDashboard;
use AIArmada\FilamentCart\Pages\LiveDashboardPage;
use AIArmada\FilamentCart\Resources\CartResource\Pages\ViewCart;
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Resources\Pages\ViewRecord;

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
        config(['filament-cart.navigation.group' => 'Operations']);

        expect(CartDashboard::getNavigationGroup())->toBe('Operations');
    });

    it('registers voucher actions from the optional voucher integration', function (): void {
        if (! class_exists(CartVoucherActions::class)) {
            $this->markTestSkipped('Vouchers package not installed.');

            return;
        }

        $page = new ViewCart;
        $recordProperty = new ReflectionProperty(ViewRecord::class, 'record');
        $recordProperty->setAccessible(true);
        $recordProperty->setValue($page, new Cart);

        $headerActionsMethod = new ReflectionMethod(ViewCart::class, 'getHeaderActions');
        $headerActionsMethod->setAccessible(true);

        /** @var array<int, Action> $actions */
        $actions = $headerActionsMethod->invoke($page);
        $actionNames = array_map(static fn (Action $action): string => $action->getName(), $actions);

        expect($actionNames)
            ->toContain('apply_voucher')
            ->toContain('show_applied_vouchers');
    });
});
