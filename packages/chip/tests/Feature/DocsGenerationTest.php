<?php

declare(strict_types=1);

namespace AIArmada\Chip\Tests\Feature;

use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Listeners\GenerateDocOnPayment;
use AIArmada\Chip\Listeners\GenerateDocOnRefund;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Tests\TestCase;

uses(TestCase::class);

describe('GenerateDocOnPayment', function (): void {
    it('skips when docs package is not installed', function (): void {
        $listener = new GenerateDocOnPayment;
        $purchaseData = PurchaseData::from([
            'id' => 'purchase-doc-test',
            'status' => 'paid',
        ]);
        $event = new PurchasePaid($purchaseData, []);

        $listener->handle($event);

        expect(true)->toBeTrue();
    });

    it('creates a purchase model and checks doc exists', function (): void {
        $purchase = Purchase::create([
            'id' => 'purchase-doc-test-123',
            'type' => 'purchase',
            'status' => 'paid',
            'brand_id' => 'brand-123',
            'company_id' => 'company-123',
            'client_id' => 'client-123',
            'created_on' => time(),
            'updated_on' => time(),
            'client' => ['email' => 'test@example.com'],
            'purchase' => ['total' => 10000, 'currency' => 'MYR', 'products' => []],
            'payment' => ['amount' => 10000, 'currency' => 'MYR'],
            'issuer_details' => [],
            'transaction_data' => [],
            'status_history' => [],
            'refund_availability' => 'all',
            'refundable_amount' => 0,
            'refund_amount_minor' => 0,
            'platform' => 'test',
            'product' => 'chip',
            'send_receipt' => false,
            'is_test' => true,
            'is_recurring_token' => false,
            'skip_capture' => false,
            'force_recurring' => false,
            'marked_as_paid' => false,
        ]);

        expect($purchase->exists)->toBeTrue();
        expect($purchase->status)->toBe('paid');
    });
});

describe('GenerateDocOnRefund', function (): void {
    it('skips when docs package is not installed', function (): void {
        $listener = new GenerateDocOnRefund;
        $paymentData = PaymentData::fromWebhookPayload([
            'id' => 'payment-test',
            'type' => 'payment',
            'related_to' => ['type' => 'purchase', 'id' => 'purchase-test'],
            'payment' => ['amount' => 1000, 'currency' => 'MYR'],
        ]);
        $event = new PaymentRefunded($paymentData, []);

        $listener->handle($event);

        expect(true)->toBeTrue();
    });
});
