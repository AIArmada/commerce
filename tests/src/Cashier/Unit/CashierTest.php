<?php

declare(strict_types=1);

use AIArmada\Cashier\Cashier;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Cashier\Models\Subscription;
use AIArmada\Cashier\Models\SubscriptionItem;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\User;

uses(CashierTestCase::class);

describe('Cashier', function () {
    it('can get the gateway manager instance', function () {
        $manager = Cashier::manager();

        expect($manager)->toBeInstanceOf(GatewayManager::class);
    });

    it('can get the default gateway', function () {
        $defaultGateway = Cashier::defaultGateway();

        expect($defaultGateway)->toBe('stripe');
    });

    it('can get available gateways', function () {
        $gateways = Cashier::availableGateways();

        expect($gateways)->toContain('stripe')
            ->and($gateways)->toContain('chip');
    });

    it('can get the default currency', function () {
        $currency = Cashier::defaultCurrency();

        expect($currency)->toBe('USD');
    });

    it('can use a custom customer model', function () {
        Cashier::useCustomerModel(User::class);

        expect(Cashier::$customerModel)->toBe(User::class);
    });

    it('can use a custom subscription model', function () {
        Cashier::useSubscriptionModel(Subscription::class);

        expect(Cashier::$subscriptionModel)->toBe(Subscription::class);
    });

    it('can use a custom subscription item model', function () {
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        expect(Cashier::$subscriptionItemModel)->toBe(SubscriptionItem::class);
    });

    it('can configure deactivate past due setting', function () {
        Cashier::deactivatePastDue(true);
        expect(Cashier::$deactivatePastDue)->toBeTrue();

        Cashier::deactivatePastDue(false);
        expect(Cashier::$deactivatePastDue)->toBeFalse();
    });

    it('can configure deactivate incomplete setting', function () {
        Cashier::deactivateIncomplete(true);
        expect(Cashier::$deactivateIncomplete)->toBeTrue();

        Cashier::deactivateIncomplete(false);
        expect(Cashier::$deactivateIncomplete)->toBeFalse();
    });

    it('can ignore routes', function () {
        Cashier::ignoreRoutes();

        expect(Cashier::$registersRoutes)->toBeFalse();
    });

    it('can ignore migrations', function () {
        Cashier::ignoreMigrations();

        expect(Cashier::$runsMigrations)->toBeFalse();
    });
});
