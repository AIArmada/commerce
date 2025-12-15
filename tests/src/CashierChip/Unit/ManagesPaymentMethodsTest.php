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
}
