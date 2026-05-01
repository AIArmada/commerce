<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class ManagesPaymentMethodsTest extends CashierChipTestCase
{
    public function test_payment_methods_returns_empty_without_chip_id(): void
    {
        $user = $this->createUser(['email' => 'test@example.com']);

        $methods = $user->paymentMethods();

        $this->assertCount(0, $methods);
    }

    public function test_find_payment_method_returns_null_without_chip_id(): void
    {
        $user = $this->createUser(['email' => 'test@example.com']);

        $method = $user->findPaymentMethod('pm_123');

        $this->assertNull($method);
    }

    public function test_has_default_payment_method(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123', 'pm_type' => 'card', 'pm_last_four' => '4242']);

        $this->assertTrue($user->hasDefaultPaymentMethod());
    }

    public function test_has_default_payment_method_false(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123', 'pm_type' => null]);

        $this->assertFalse($user->hasDefaultPaymentMethod());
    }

    public function test_default_payment_method_returns_null_without_default(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123', 'pm_type' => null]);

        $this->assertNull($user->defaultPaymentMethod());
    }

    public function test_delete_payment_method_returns_early_without_chip_id(): void
    {
        $user = $this->createUser(['email' => 'test@example.com']);

        // Should not throw
        $user->deletePaymentMethod('pm_123');

        $this->assertTrue(true);
    }

    public function test_delete_payment_methods(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123', 'pm_type' => 'card', 'pm_last_four' => '4242']);

        $user->deletePaymentMethods();

        $this->assertNull($user->fresh()->pm_type);
        $this->assertNull($user->fresh()->pm_last_four);
    }

    public function test_update_default_payment_method_persists_default_pm_id(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $token = $this->fakeChip->addRecurringToken('cli_123', [
            'id' => 'tok_primary',
            'card_brand' => 'Visa',
            'last_4' => '4242',
        ]);

        $user->updateDefaultPaymentMethod('tok_primary');

        $freshUser = $user->fresh();

        $this->assertNotNull($freshUser);
        $this->assertSame('tok_primary', $freshUser->default_pm_id);
        $this->assertSame('Visa', $freshUser->pm_type);
        $this->assertSame('4242', $freshUser->pm_last_four);
        $this->assertSame('tok_primary', $token['id']);
    }

    public function test_default_payment_method_prefers_saved_default_pm_id(): void
    {
        $user = $this->createUser([
            'chip_id' => 'cli_456',
            'default_pm_id' => 'tok_preferred',
            'pm_type' => 'card',
        ]);

        $this->fakeChip->addRecurringToken('cli_456', [
            'id' => 'tok_other',
            'card_brand' => 'Mastercard',
            'last_4' => '1111',
        ]);

        $this->fakeChip->addRecurringToken('cli_456', [
            'id' => 'tok_preferred',
            'card_brand' => 'Visa',
            'last_4' => '4242',
        ]);

        $paymentMethod = $user->defaultPaymentMethod();

        $this->assertNotNull($paymentMethod);
        $this->assertSame('tok_preferred', $paymentMethod?->id());
    }
}
