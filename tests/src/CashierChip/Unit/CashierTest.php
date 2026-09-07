<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

uses(CashierChipTestCase::class);

describe('Cashier', function (): void {
    afterEach(function (): void {
        Cashier::restoreOctaneDefaults();
    });

    it('version', function (): void {
        $this->assertEquals('1.0.0', Cashier::VERSION);
    });

    it('find billable returns null without chip id', function (): void {
        $result = Cashier::findBillable(null);

        $this->assertNull($result);
    });

    it('find billable returns user', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_find_test_123']);

        $result = Cashier::findBillable('cli_find_test_123');

        $this->assertNotNull($result);
        $this->assertEquals($user->id, $result->id);
    });

    it('chip returns fake', function (): void {
        $chip = Cashier::chip();

        $this->assertInstanceOf(FakeChipCollectService::class, $chip);
    });

    it('is fake', function (): void {
        $this->assertTrue(Cashier::isFake());
    });

    it('get fake', function (): void {
        $fake = Cashier::getFake();

        $this->assertInstanceOf(FakeChipCollectService::class, $fake);
    });

    it('format amount', function (): void {
        $formatted = Cashier::formatAmount(1000, 'MYR');

        $this->assertIsString($formatted);
    });

    it('format amount with custom formatter', function (): void {
        Cashier::formatCurrencyUsing(function ($amount, $currency) {
            return "CUSTOM: {$currency} {$amount}";
        });

        $formatted = Cashier::formatAmount(1000, 'MYR');

        $this->assertEquals('CUSTOM: MYR 1000', $formatted);
    });

    it('ignore routes', function (): void {
        Cashier::ignoreRoutes();

        $this->assertFalse(Cashier::$registersRoutes);
    });

    it('keep past due subscriptions active', function (): void {
        Cashier::keepPastDueSubscriptionsActive();

        $this->assertFalse(Cashier::$deactivatePastDue);
    });

    it('keep incomplete subscriptions active', function (): void {
        Cashier::keepIncompleteSubscriptionsActive();

        $this->assertFalse(Cashier::$deactivateIncomplete);
    });

    it('use customer model', function (): void {
        $original = Cashier::$customerModel;

        Cashier::useCustomerModel(User::class);

        $this->assertEquals(User::class, Cashier::$customerModel);

        // Reset
        Cashier::useCustomerModel($original);
    });

    it('use subscription model', function (): void {
        $original = Cashier::$subscriptionModel;

        Cashier::useSubscriptionModel(Subscription::class);

        $this->assertEquals(Subscription::class, Cashier::$subscriptionModel);

        // Reset
        Cashier::useSubscriptionModel($original);
    });

    it('use subscription item model', function (): void {
        $original = Cashier::$subscriptionItemModel;

        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        $this->assertEquals(SubscriptionItem::class, Cashier::$subscriptionItemModel);

        // Reset
        Cashier::useSubscriptionItemModel($original);
    });

    it('reset fake', function (): void {
        // Should not throw
        Cashier::resetFake();

        $this->assertTrue(true);
    });
});
