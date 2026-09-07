<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Checkout;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('PerformsCharges', function (): void {
    it('charge returns payment', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->charge(1000);

        $this->assertInstanceOf(Payment::class, $payment);
    });

    it('pay returns payment', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->pay(1000);

        $this->assertInstanceOf(Payment::class, $payment);
    });

    it('pay with returns payment', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->payWith(1000, ['card', 'fpx']);

        $this->assertInstanceOf(Payment::class, $payment);
    });

    it('create payment returns payment', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->createPayment(1000);

        $this->assertInstanceOf(Payment::class, $payment);
    });

    it('create payment with options', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->createPayment(1000, [
            'product_name' => 'Test Product',
            'currency' => 'MYR',
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
            'webhook_url' => 'https://example.com/webhook',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
    });

    it('find payment returns null on error', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->findPayment('non_existent_id');

        $this->assertNull($payment);
    });

    it('charge with recurring token', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->chargeWithRecurringToken(1000, null);

        $this->assertInstanceOf(Payment::class, $payment);
    });

    it('checkout returns checkout', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $checkout = $user->checkout(1000);

        $this->assertInstanceOf(Checkout::class, $checkout);
    });

    it('checkout with options', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $checkout = $user->checkout(1000, [
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
    });

    it('checkout charge returns checkout', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $checkout = $user->checkoutCharge(1000, 'Test Product', 2);

        $this->assertInstanceOf(Checkout::class, $checkout);

        $payload = $checkout->toArray();
        $this->assertSame(1000, $payload['purchase']['products'][0]['price']);
        $this->assertSame('2', $payload['purchase']['products'][0]['quantity']);
    });

    it('rejects a checkout charge total outside the configured bounds', function (): void {
        config()->set('cashier-chip.billing.max_amount_minor', 1500);
        $user = $this->createUser(['chip_id' => 'cli_123']);

        expect(fn () => $user->checkoutCharge(1000, 'Test Product', 2))
            ->toThrow(InvalidArgumentException::class);
    });

    it('charge without chip id', function (): void {
        $user = $this->createUser(['email' => 'test@example.com', 'name' => 'Test User']);

        $payment = $user->charge(1000);

        $this->assertInstanceOf(Payment::class, $payment);
    });

    it('create payment with skip capture', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->createPayment(1000, [
            'skip_capture' => true,
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
    });

    it('create payment with force recurring', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->createPayment(1000, [
            'force_recurring' => true,
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
    });
});
