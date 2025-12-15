<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Checkout;
use AIArmada\CashierChip\CheckoutBuilder;
use AIArmada\CashierChip\Payment;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class CheckoutTest extends CashierChipTestCase
{
    public function test_guest_returns_checkout_builder(): void
    {
        $builder = Checkout::guest();

        $this->assertInstanceOf(CheckoutBuilder::class, $builder);
    }

    public function test_customer_returns_checkout_builder(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $builder = Checkout::customer($user);

        $this->assertInstanceOf(CheckoutBuilder::class, $builder);
    }

    public function test_can_get_id(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $this->assertEquals('pur_123', $checkout->id());
    }

    public function test_can_get_owner(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout($user, $purchase);

        $this->assertSame($user, $checkout->owner());
    }

    public function test_owner_can_be_null(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $this->assertNull($checkout->owner());
    }

    public function test_can_get_chip_purchase(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $this->assertSame($purchase, $checkout->asChipPurchase());
    }

    public function test_can_convert_to_payment(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $checkout = new Checkout(null, $purchase);

        $payment = $checkout->asPayment();

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals('pur_123', $payment->id());
    }

    public function test_to_array(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $array = $checkout->toArray();

        $this->assertIsArray($array);
    }

    public function test_to_json(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $json = $checkout->toJson();

        $this->assertJson($json);
    }

    public function test_json_serialize(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $checkout = new Checkout(null, $purchase);

        $serialized = $checkout->jsonSerialize();

        $this->assertIsArray($serialized);
    }

    public function test_dynamic_property_access(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created', 'reference' => 'ref_123']);
        $checkout = new Checkout(null, $purchase);

        $this->assertEquals('pur_123', $checkout->id);
        $this->assertEquals('created', $checkout->status);
    }

    public function test_can_get_url(): void
    {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'created',
            'checkout_url' => 'https://chip.example.com/checkout/pur_123',
        ]);
        $checkout = new Checkout(null, $purchase);

        $this->assertEquals('https://chip.example.com/checkout/pur_123', $checkout->url());
    }
}
