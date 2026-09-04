<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Feature;

use AIArmada\CashierChip\Actions\RefundChipPayment;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Testing\FakeChipClient;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Commerce\Tests\TestCase;

\uses(TestCase::class);
describe('RefundChipPayment', function (): void {
    it('refunds a purchase', function (): void {
        $fakeClient = new FakeChipClient;
        $purchase = $fakeClient->createPurchase([]);
        $purchaseId = $purchase['id'];

        Cashier::fake($fakeClient);

        $result = RefundChipPayment::run($purchaseId);

        expect($result)->toBeInstanceOf(PaymentData::class)
            ->and($result->payment_type)->toBe('refund');
    });

    it('refunds a purchase with partial amount', function (): void {
        $fakeClient = new FakeChipClient;
        $purchase = $fakeClient->createPurchase([]);
        $purchaseId = $purchase['id'];

        Cashier::fake($fakeClient);

        $result = RefundChipPayment::run($purchaseId, 2500);

        expect($result)->toBeInstanceOf(PaymentData::class)
            ->and($result->payment_type)->toBe('refund');
    });
});
