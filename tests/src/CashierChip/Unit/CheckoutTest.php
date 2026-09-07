<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Checkout;
use AIArmada\CashierChip\Billing\CheckoutBuilder;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('Checkout', function (): void {
    it('guest returns checkout builder', function (): void {
        $builder = Checkout::guest();

        $this->assertInstanceOf(CheckoutBuilder::class, $builder);
    });

    it('customer returns checkout builder', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $builder = Checkout::customer($user);

        $this->assertInstanceOf(CheckoutBuilder::class, $builder);
    });

    it('can get id', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $this->assertEquals('pur_123', $checkout->id());
    });

    it('can get owner', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout($user, $purchase);

        $this->assertSame($user, $checkout->owner());
    });

    it('owner can be null', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $this->assertNull($checkout->owner());
    });

    it('can get chip purchase', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $this->assertSame($purchase, $checkout->asChipPurchase());
    });

    it('can convert to payment', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $checkout = new Checkout(null, $purchase);

        $payment = $checkout->asPayment();

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals('pur_123', $payment->id());
    });

    it('to array', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $array = $checkout->toArray();

        $this->assertIsArray($array);
    });

    it('to json', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $json = $checkout->toJson();

        $this->assertJson($json);
    });

    it('json serialize', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $serialized = $checkout->jsonSerialize();

        $this->assertIsArray($serialized);
    });

    it('dynamic property access', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created', 'reference' => 'ref_123']);
        $checkout = new Checkout(null, $purchase);

        $this->assertEquals('pur_123', $checkout->id);
        $this->assertEquals('created', $checkout->status);
    });

    it('can get url', function (): void {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'created',
            'checkout_url' => 'https://chip.example.com/checkout/pur_123',
        ]);
        $checkout = new Checkout(null, $purchase);

        $this->assertEquals('https://chip.example.com/checkout/pur_123', $checkout->url());
    });
});
