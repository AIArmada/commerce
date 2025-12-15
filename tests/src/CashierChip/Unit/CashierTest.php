<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

class CashierTest extends CashierChipTestCase
{
    protected function tearDown(): void
    {
        // Reset static properties
        Cashier::$registersRoutes = true;
        Cashier::$deactivatePastDue = true;
        Cashier::$deactivateIncomplete = true;
        Cashier::formatCurrencyUsing(null);

        parent::tearDown();
    }

    public function test_version(): void
    {
        $this->assertEquals('1.0.0', Cashier::VERSION);
    }

    public function test_find_billable_returns_null_without_chip_id(): void
    {
        $result = Cashier::findBillable(null);

        $this->assertNull($result);
    }

    public function test_find_billable_returns_user(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_find_test_123']);

        $result = Cashier::findBillable('cli_find_test_123');

        $this->assertNotNull($result);
        $this->assertEquals($user->id, $result->id);
    }

    public function test_chip_returns_fake(): void
    {
        $chip = Cashier::chip();

        $this->assertInstanceOf(FakeChipCollectService::class, $chip);
    }

    public function test_is_fake(): void
    {
        $this->assertTrue(Cashier::isFake());
    }

    public function test_get_fake(): void
    {
        $fake = Cashier::getFake();

        $this->assertInstanceOf(FakeChipCollectService::class, $fake);
    }

    public function test_format_amount(): void
    {
        $formatted = Cashier::formatAmount(1000, 'MYR');

        $this->assertIsString($formatted);
    }

    public function test_format_amount_with_custom_formatter(): void
    {
        Cashier::formatCurrencyUsing(function ($amount, $currency) {
            return "CUSTOM: {$currency} {$amount}";
        });

        $formatted = Cashier::formatAmount(1000, 'MYR');

        $this->assertEquals('CUSTOM: MYR 1000', $formatted);
    }

    public function test_ignore_routes(): void
    {
        Cashier::ignoreRoutes();

        $this->assertFalse(Cashier::$registersRoutes);
    }

    public function test_keep_past_due_subscriptions_active(): void
    {
        Cashier::keepPastDueSubscriptionsActive();

        $this->assertFalse(Cashier::$deactivatePastDue);
    }

    public function test_keep_incomplete_subscriptions_active(): void
    {
        Cashier::keepIncompleteSubscriptionsActive();

        $this->assertFalse(Cashier::$deactivateIncomplete);
    }

    public function test_use_customer_model(): void
    {
        $original = Cashier::$customerModel;

        Cashier::useCustomerModel(User::class);

        $this->assertEquals(User::class, Cashier::$customerModel);

        // Reset
        Cashier::useCustomerModel($original);
    }

    public function test_use_subscription_model(): void
    {
        $original = Cashier::$subscriptionModel;

        Cashier::useSubscriptionModel(Subscription::class);

        $this->assertEquals(Subscription::class, Cashier::$subscriptionModel);

        // Reset
        Cashier::useSubscriptionModel($original);
    }

    public function test_use_subscription_item_model(): void
    {
        $original = Cashier::$subscriptionItemModel;

        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        $this->assertEquals(SubscriptionItem::class, Cashier::$subscriptionItemModel);

        // Reset
        Cashier::useSubscriptionItemModel($original);
    }

    public function test_reset_fake(): void
    {
        // Should not throw
        Cashier::resetFake();

        $this->assertTrue(true);
    }
}
