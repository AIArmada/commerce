<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class ProratesTest extends CashierChipTestCase
{
    public function test_no_prorate()
    {
        $subscription = new Subscription;
        $subscription->noProrate();

        $this->assertEquals('none', $subscription->prorateBehavior());
    }

    public function test_prorate()
    {
        $subscription = new Subscription;
        $subscription->noProrate(); // Set to none first
        $subscription->prorate();

        $this->assertEquals('create_prorations', $subscription->prorateBehavior());
    }

    public function test_always_invoice()
    {
        $subscription = new Subscription;
        $subscription->alwaysInvoice();

        $this->assertEquals('always_invoice', $subscription->prorateBehavior());
    }

    public function test_set_proration_behavior()
    {
        $subscription = new Subscription;
        $subscription->setProrationBehavior('custom_behavior');

        $this->assertEquals('custom_behavior', $subscription->prorateBehavior());
    }

    public function test_default_proration_behavior()
    {
        $subscription = new Subscription;

        $this->assertEquals('create_prorations', $subscription->prorateBehavior());
    }
}
