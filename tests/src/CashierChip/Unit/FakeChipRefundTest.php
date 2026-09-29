<?php

declare(strict_types=1);

use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Chip\Data\ClientDetailsData;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('FakeChipCollectService refunds', function (): void {
    it('routes an armed pending refund through the union branch as PurchaseData', function (): void {
        $service = new FakeChipCollectService;
        $created = $service->createPurchase(['purchase' => ['currency' => 'MYR']]);

        $service->getFakeClient()->pendingRefundOnce();

        $pending = $service->refundPurchase($created->id);

        expect($pending)->toBeInstanceOf(PurchaseData::class)
            ->and($pending->status)->toBe('pending_refund');
    });

    it('consumes the pending-refund lever after one use', function (): void {
        $service = new FakeChipCollectService;
        $created = $service->createPurchase(['purchase' => ['currency' => 'MYR']]);

        $service->getFakeClient()->pendingRefundOnce();
        $service->refundPurchase($created->id);

        $completed = $service->refundPurchase($created->id);

        expect($completed)->toBeInstanceOf(PaymentData::class);
    });

    it('maps the full client details on checkout purchases', function (): void {
        $service = new FakeChipCollectService;

        $purchase = $service->createCheckoutPurchase(
            [ProductData::from(['name' => 'Item', 'price' => 1000, 'currency' => 'MYR'])],
            ClientDetailsData::from(['email' => 'buyer@example.com', 'country' => 'MY', 'city' => 'KL']),
        );

        expect($purchase->client->email)->toBe('buyer@example.com')
            ->and($purchase->client->country)->toBe('MY')
            ->and($purchase->client->city)->toBe('KL');
    });
});
