<?php

declare(strict_types=1);

use AIArmada\Cashier\Support\InvoiceStatus;
use AIArmada\Cashier\Support\SubscriptionStatus;
use AIArmada\Cashier\Support\UnifiedInvoice;
use AIArmada\Cashier\Support\UnifiedSubscription;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

uses(CashierTestCase::class);

describe('Unified records', function (): void {
    it('uses immutable DTOs instead of table-backed records', function (): void {
        $subscription = new UnifiedSubscription(
            id: 'sub_1',
            gateway: 'chip',
            userId: 'user-123',
            type: 'default',
            planId: 'basic',
            amount: 1000,
            currency: 'MYR',
            quantity: 1,
            status: SubscriptionStatus::Active,
            trialEndsAt: null,
            endsAt: null,
            nextBillingDate: null,
            createdAt: CarbonImmutable::now(),
            original: new class extends Model {},
        );

        $invoice = new UnifiedInvoice(
            id: 'in_1',
            gateway: 'chip',
            userId: 'user-123',
            number: 'INV-1',
            amount: 1000,
            currency: 'MYR',
            status: InvoiceStatus::Paid,
            date: CarbonImmutable::now(),
            dueDate: null,
            paidAt: null,
            pdfUrl: null,
            original: new stdClass,
        );

        expect((new ReflectionClass($subscription))->isReadOnly())->toBeTrue()
            ->and((new ReflectionClass($invoice))->isReadOnly())->toBeTrue()
            ->and($subscription->id)->toBe('sub_1')
            ->and($invoice->number)->toBe('INV-1');
    });
});
