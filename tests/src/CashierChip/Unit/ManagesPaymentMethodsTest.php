<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('ManagesPaymentMethods', function (): void {
    it('payment methods returns empty without chip id', function (): void {
        $user = $this->createUser(['email' => 'test@example.com']);

        $methods = $user->paymentMethods();

        $this->assertCount(0, $methods);
    });

    it('find payment method returns null without chip id', function (): void {
        $user = $this->createUser(['email' => 'test@example.com']);

        $method = $user->findPaymentMethod('pm_123');

        $this->assertNull($method);
    });

    it('has default payment method', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Cashier::paymentMethodStore()->saveForBillable($user, 'tok_default', [
            'type' => 'card',
            'brand' => 'Visa',
            'last_four' => '4242',
        ], true);

        $this->assertTrue($user->hasDefaultPaymentMethod());
    });

    it('has default payment method false', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->hasDefaultPaymentMethod());
    });

    it('default payment method returns null without default', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertNull($user->defaultPaymentMethod());
    });

    it('delete payment method returns early without chip id', function (): void {
        $user = $this->createUser(['email' => 'test@example.com']);

        // Should not throw
        $user->deletePaymentMethod('pm_123');

        $this->assertTrue(true);
    });

    it('delete payment methods', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        Cashier::paymentMethodStore()->saveForBillable($user, 'tok_default', [
            'type' => 'card',
            'brand' => 'Visa',
            'last_four' => '4242',
        ], true);

        $user->deletePaymentMethods();

        $this->assertFalse($user->fresh()->hasPaymentMethod());
    });

    it('update default payment method persists default payment method', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $token = $this->fakeChip->addRecurringToken('cli_123', [
            'id' => 'tok_primary',
            'payment_method' => 'visa',
            'description' => '**** **** **** 4242',
        ]);

        $user->updateDefaultPaymentMethod('tok_primary');

        $freshUser = $user->fresh();

        $this->assertNotNull($freshUser);
        $this->assertSame('tok_primary', $freshUser->default_pm_id);
        $this->assertSame('visa', $freshUser->pm_type);
        $this->assertNull($freshUser->pm_last_four);
        $this->assertSame('tok_primary', $token['id']);
    });

    it('default payment method prefers saved default payment method', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_456']);
        Cashier::paymentMethodStore()->saveForBillable($user, 'tok_preferred', [
            'type' => 'card',
            'brand' => 'Visa',
        ], true);

        $this->fakeChip->addRecurringToken('cli_456', [
            'id' => 'tok_other',
            'payment_method' => 'mastercard',
            'description' => '**** **** **** 1111',
        ]);

        $this->fakeChip->addRecurringToken('cli_456', [
            'id' => 'tok_preferred',
            'payment_method' => 'visa',
            'description' => '**** **** **** 4242',
        ]);

        $paymentMethod = $user->defaultPaymentMethod();

        $this->assertNotNull($paymentMethod);
        $this->assertSame('tok_preferred', $paymentMethod?->id());
    });
});
