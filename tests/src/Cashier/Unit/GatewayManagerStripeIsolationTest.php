<?php

declare(strict_types=1);

use AIArmada\Cashier\GatewayManager;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

it('detects stripe availability from laravel/cashier presence', function (): void {
    $detector = app(GatewayDetector::class);

    expect($detector->isAvailable('stripe'))->toBeTrue();
});

it('includes stripe in supported gateways when laravel/cashier is installed', function (): void {
    config()->set('cashier.gateways', [
        'stripe' => ['driver' => 'stripe'],
        'chip' => ['driver' => 'chip'],
    ]);

    $manager = app(GatewayManager::class);

    expect($manager->supportedGateways())->toContain('stripe')
        ->and($manager->supportsGateway('stripe'))->toBeTrue();
});
