<?php

declare(strict_types=1);

use AIArmada\Cashier\Cashier;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\User;

uses(CashierTestCase::class);

describe('Cashier', function (): void {
    it('can get the gateway manager instance', function (): void {
        $manager = Cashier::manager();

        expect($manager)->toBeInstanceOf(GatewayManager::class);
    });

    it('can get the default gateway', function (): void {
        $defaultGateway = Cashier::defaultGateway();

        expect($defaultGateway)->toBe('stripe');
    });

    it('can get available gateways', function (): void {
        $gateways = Cashier::availableGateways();

        expect($gateways)->toContain('stripe')
            ->and($gateways)->toContain('chip');
    });

    it('can get the default currency', function (): void {
        $currency = Cashier::defaultCurrency();

        expect($currency)->toBe('USD');
    });

    it('can use a custom customer model', function (): void {
        Cashier::useCustomerModel(User::class);

        expect(Cashier::$customerModel)->toBe(User::class);
    });

<<<<<<< Updated upstream
<<<<<<< Updated upstream
    it('can use a custom subscription model', function (): void {
        Cashier::useSubscriptionModel(Subscription::class);

        expect(Cashier::$subscriptionModel)->toBe(Subscription::class);
    });

    it('can use a custom subscription item model', function (): void {
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        expect(Cashier::$subscriptionItemModel)->toBe(SubscriptionItem::class);
    });

    it('can configure deactivate past due setting', function (): void {
=======
=======
>>>>>>> Stashed changes
    it('can configure deactivate past due setting', function () {
>>>>>>> Stashed changes
        Cashier::deactivatePastDue(true);
        expect(Cashier::$deactivatePastDue)->toBeTrue();

        Cashier::deactivatePastDue(false);
        expect(Cashier::$deactivatePastDue)->toBeFalse();
    });

    it('can configure deactivate incomplete setting', function (): void {
        Cashier::deactivateIncomplete(true);
        expect(Cashier::$deactivateIncomplete)->toBeTrue();

        Cashier::deactivateIncomplete(false);
        expect(Cashier::$deactivateIncomplete)->toBeFalse();
    });

    it('can ignore routes', function (): void {
        Cashier::ignoreRoutes();

        expect(Cashier::$registersRoutes)->toBeFalse();
    });
<<<<<<< Updated upstream
<<<<<<< Updated upstream

    it('can ignore migrations', function (): void {
        Cashier::ignoreMigrations();

        expect(Cashier::$runsMigrations)->toBeFalse();
    });
=======
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
});
