<?php

declare(strict_types=1);

use AIArmada\Cashier\CashierServiceProvider;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Cashier\Support\CartIntegrationRegistrar;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('CashierServiceProvider - Additional Coverage', function (): void {
    /* Config-merge/singleton/alias removed; covered by Feature/ServiceProviderTest. */

    it('registers CartIntegrationRegistrar as singleton', function (): void {
        $registrar1 = app(CartIntegrationRegistrar::class);
        $registrar2 = app(CartIntegrationRegistrar::class);

        expect($registrar1)->toBe($registrar2);
    });

    it('provides expected services', function (): void {
        $provider = new CashierServiceProvider(app());
        $provides = $provider->provides();

        expect($provides)->toContain(GatewayManager::class)
            ->and($provides)->toContain(CartIntegrationRegistrar::class)
            ->and($provides)->toContain('cashier');
    });

});
