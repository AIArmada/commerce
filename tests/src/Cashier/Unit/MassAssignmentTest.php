<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Cashier\Unit;

use AIArmada\Cashier\Models\UnifiedInvoiceRecord;
use AIArmada\Cashier\Models\UnifiedSubscriptionRecord;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

final class MassAssignmentTest extends CashierTestCase
{
    public function test_unified_subscription_record_protects_status_amount_and_gateway_ids(): void
    {
        $subscription = new UnifiedSubscriptionRecord([
            'user_id' => 'user-123',
            'type' => 'default',
            'plan_id' => 'basic',
            'quantity' => 1,
            'stripe_id' => 'sub_stripe',
            'chip_id' => 'sub_chip',
            'status' => 'active',
            'amount' => 1000,
        ]);

        $this->assertSame('user-123', $subscription->user_id);
        $this->assertSame('default', $subscription->type);
        $this->assertSame('basic', $subscription->plan_id);
        $this->assertSame(1, $subscription->quantity);
        $this->assertNull($subscription->stripe_id);
        $this->assertNull($subscription->chip_id);
        $this->assertNull($subscription->status);
        $this->assertNull($subscription->amount);
    }

    public function test_unified_invoice_record_protects_status_amount_and_gateway_ids(): void
    {
        $invoice = new UnifiedInvoiceRecord([
            'user_id' => 'user-123',
            'number' => 'INV-1',
            'currency' => 'MYR',
            'stripe_id' => 'in_stripe',
            'chip_id' => 'purchase_chip',
            'status' => 'paid',
            'amount' => 1000,
        ]);

        $this->assertSame('user-123', $invoice->user_id);
        $this->assertSame('INV-1', $invoice->number);
        $this->assertSame('MYR', $invoice->currency);
        $this->assertNull($invoice->stripe_id);
        $this->assertNull($invoice->chip_id);
        $this->assertNull($invoice->status);
        $this->assertNull($invoice->amount);
    }
}
