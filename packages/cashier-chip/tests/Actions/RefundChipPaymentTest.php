<?php

declare(strict_types=1);

use AIArmada\CashierChip\Actions\RefundChipPayment;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Testing\FakeChipClient;
use AIArmada\CashierChip\Tests\TestCase;
use AIArmada\Chip\Data\PurchaseData;

uses(TestCase::class);

describe('RefundChipPayment', function (): void {
    it('refunds a purchase', function (): void {
        $fakeClient = new FakeChipClient;
        $purchase = $fakeClient->createPurchase([]);
        $purchaseId = $purchase['id'];

        Cashier::fake($fakeClient);

        $result = app(RefundChipPayment::class)->refund($purchaseId);

        expect($result)->toBeInstanceOf(PurchaseData::class);
        expect($result->status)->toBe('refunded');
    });

    it('refunds a purchase with partial amount', function (): void {
        $fakeClient = new FakeChipClient;
        $purchase = $fakeClient->createPurchase([]);
        $purchaseId = $purchase['id'];

        Cashier::fake($fakeClient);

        $result = app(RefundChipPayment::class)->refund($purchaseId, 2500);

        expect($result)->toBeInstanceOf(PurchaseData::class);
        expect($result->status)->toBe('refunded');
    });
});
