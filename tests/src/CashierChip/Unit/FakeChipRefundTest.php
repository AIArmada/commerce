<?php

declare(strict_types=1);

use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Chip\Data\ClientDetailsData;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipValidationException;
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

    it('computes fake checkout totals with the server line-total formula', function (): void {
        $service = new FakeChipCollectService;
        $client = ClientDetailsData::from(['email' => 'buyer@example.com']);

        // Fractional quantity must not truncate (149, not 99).
        $fractional = $service->createCheckoutPurchase(
            [ProductData::from(['name' => 'Disc', 'price' => 100, 'quantity' => '1.5', 'discount' => 1, 'currency' => 'MYR'])],
            $client,
        );

        // Tax applies to the net line.
        $taxed = $service->createCheckoutPurchase(
            [ProductData::from(['name' => 'DT', 'price' => 100, 'quantity' => '1.5', 'discount' => 1, 'tax_percent' => 6.0, 'currency' => 'MYR'])],
            $client,
        );

        // tpo: the override is the final line total.
        $overridden = $service->createCheckoutPurchase(
            [ProductData::from(['name' => 'TPO', 'price' => 1000, 'quantity' => 3, 'total_price_override' => 2500, 'currency' => 'MYR'])],
            $client,
        );

        expect($fractional->getAmountInCents())->toBe(149)
            ->and($taxed->getAmountInCents())->toBe(158)
            ->and($overridden->getAmountInCents())->toBe(2500);
    });

    it('rejects fake checkout products the server would reject', function (array $fields): void {
        $service = new FakeChipCollectService;
        $product = array_merge(['name' => 'Bad', 'price' => 100], $fields);

        expect(fn () => $service->getFakeClient()->createPurchase([
            'purchase' => ['currency' => 'MYR', 'products' => [$product]],
        ]))->toThrow(ChipValidationException::class);
    })->with([
        'non-numeric quantity' => [['quantity' => 'lots']],
        'negative quantity' => [['quantity' => -1]],
        'negative price' => [['price' => -100]],
        'negative discount' => [['discount' => -5]],
        'fractional price' => [['price' => 10.5]],
        'tax above 100' => [['tax_percent' => 101]],
        'tax past 2dp' => [['tax_percent' => '6.555']],
        'negative override' => [['total_price_override' => -1]],
    ]);

    it('rejects a fake checkout discount above the line gross like the server', function (): void {
        $service = new FakeChipCollectService;

        // The server 400s product_subtotal_negative; the fake must
        // throw instead of storing a zero-total purchase.
        expect(fn () => $service->createCheckoutPurchase(
            [ProductData::from(['name' => 'DG', 'price' => 100, 'quantity' => 3, 'discount' => 500, 'currency' => 'MYR'])],
            ClientDetailsData::from(['email' => 'buyer@example.com']),
        ))->toThrow(ChipValidationException::class, 'discount cannot be larger than price times quantity');
    });
});
