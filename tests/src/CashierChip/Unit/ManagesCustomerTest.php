<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Exceptions\CustomerAlreadyCreated;
use AIArmada\CashierChip\Exceptions\InvalidCustomer;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class ManagesCustomerTest extends CashierChipTestCase
{
    public function test_chip_id(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertEquals('cli_123', $user->chipId());
    }

    public function test_has_chip_id(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertTrue($user->hasChipId());
    }

    public function test_has_chip_id_false(): void
    {
        $user = $this->createUser(['email' => 'test@example.com']);

        $this->assertFalse($user->hasChipId());
    }

    public function test_chip_name(): void
    {
        $user = $this->createUser(['name' => 'John Doe']);

        $this->assertEquals('John Doe', $user->chipName());
    }

    public function test_chip_email(): void
    {
        $user = $this->createUser(['email' => 'john@example.com']);

        $this->assertEquals('john@example.com', $user->chipEmail());
    }

    public function test_chip_phone(): void
    {
        $user = $this->createUser(['phone' => '+60123456789']);

        $this->assertEquals('+60123456789', $user->chipPhone());
    }

    public function test_chip_country(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        // Default is 'MY'
        $this->assertEquals('MY', $user->chipCountry());
    }

    public function test_chip_address(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        // Default is empty array
        $this->assertEquals([], $user->chipAddress());
    }

    public function test_preferred_currency(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertEquals('MYR', $user->preferredCurrency());
    }

    public function test_balance_returns_formatted_zero(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $balance = $user->balance();

        $this->assertIsString($balance);
    }

    public function test_raw_balance_returns_zero(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertEquals(0, $user->rawBalance());
    }

    public function test_has_balance(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        // rawBalance returns 0, so hasBalance is false
        $this->assertFalse($user->hasBalance());
    }

    public function test_has_negative_balance(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->hasNegativeBalance());
    }

    public function test_is_not_tax_exempt(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertTrue($user->isNotTaxExempt());
    }

    public function test_is_tax_exempt(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->isTaxExempt());
    }

    public function test_reverse_charge_applies(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->reverseChargeApplies());
    }

    public function test_create_as_chip_customer_throws_if_exists(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->expectException(CustomerAlreadyCreated::class);

        $user->createAsChipCustomer();
    }

    public function test_as_chip_customer_throws_if_not_exists(): void
    {
        $user = $this->createUser(['email' => 'test@example.com']);

        $this->expectException(InvalidCustomer::class);

        $user->asChipCustomer();
    }

    public function test_update_chip_customer_throws_if_not_exists(): void
    {
        $user = $this->createUser(['email' => 'test@example.com']);

        $this->expectException(InvalidCustomer::class);

        $user->updateChipCustomer(['full_name' => 'New Name']);
    }
}
