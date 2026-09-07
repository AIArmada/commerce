<?php

declare(strict_types=1);

use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('Prorates', function (): void {
    it('no prorate', function (): void {
        $subscription = new Subscription;
        $subscription->noProrate();

        $this->assertEquals('none', $subscription->prorateBehavior());
    });

    it('prorate', function (): void {
        $subscription = new Subscription;
        $subscription->noProrate(); // Set to none first
        $subscription->prorate();

        $this->assertEquals('create_prorations', $subscription->prorateBehavior());
    });

    it('always invoice', function (): void {
        $subscription = new Subscription;
        $subscription->alwaysInvoice();

        $this->assertEquals('always_invoice', $subscription->prorateBehavior());
    });

    it('set proration behavior', function (): void {
        $subscription = new Subscription;
        $subscription->setProrationBehavior('custom_behavior');

        $this->assertEquals('custom_behavior', $subscription->prorateBehavior());
    });

    it('default proration behavior', function (): void {
        $subscription = new Subscription;

        $this->assertEquals('create_prorations', $subscription->prorateBehavior());
    });
});
