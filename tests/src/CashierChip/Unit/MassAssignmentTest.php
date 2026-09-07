<?php

declare(strict_types=1);

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Payment\StoredPaymentMethod;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('MassAssignment', function (): void {
    it('subscription protects owner status and gateway id', function (): void {
        $subscription = new Subscription([
            'billable_type' => 'user',
            'billable_id' => 'user-123',
            'type' => 'default',
            'chip_price' => 'price_basic',
            'owner_type' => 'tenant',
            'owner_id' => 'tenant-123',
            'chip_id' => 'sub_external',
            'chip_status' => SubscriptionStatus::Active,
        ]);

        $this->assertSame('user', $subscription->billable_type);
        $this->assertSame('user-123', $subscription->billable_id);
        $this->assertSame('default', $subscription->type);
        $this->assertSame('price_basic', $subscription->chip_price);
        $this->assertNull($subscription->owner_type);
        $this->assertNull($subscription->owner_id);
        $this->assertSame('sub_external', $subscription->chip_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->chip_status);
    });

    it('subscription item protects owner gateway id and amount', function (): void {
        $item = new SubscriptionItem([
            'subscription_id' => 'subscription-123',
            'chip_product' => 'product_basic',
            'chip_price' => 'price_basic',
            'quantity' => 2,
            'owner_type' => 'tenant',
            'owner_id' => 'tenant-123',
            'chip_id' => 'item_external',
            'unit_amount' => 1000,
        ]);

        $this->assertSame('subscription-123', $item->subscription_id);
        $this->assertSame('product_basic', $item->chip_product);
        $this->assertSame('price_basic', $item->chip_price);
        $this->assertSame(2, $item->quantity);
        $this->assertNull($item->owner_type);
        $this->assertNull($item->owner_id);
        $this->assertNull($item->chip_id);
        $this->assertNull($item->unit_amount);
    });

    it('stored payment method protects owner tuple', function (): void {
        $paymentMethod = new StoredPaymentMethod([
            'billable_type' => 'user',
            'billable_id' => 'user-123',
            'type' => 'card',
            'owner_type' => 'tenant',
            'owner_id' => 'tenant-123',
        ]);

        $this->assertSame('user', $paymentMethod->billable_type);
        $this->assertSame('user-123', $paymentMethod->billable_id);
        $this->assertNull($paymentMethod->getAttribute('recurring_token'));
        $this->assertNotContains('recurring_token', $paymentMethod->getFillable());
        $this->assertSame('card', $paymentMethod->type);
        $this->assertNull($paymentMethod->owner_type);
        $this->assertNull($paymentMethod->owner_id);
    });
});
