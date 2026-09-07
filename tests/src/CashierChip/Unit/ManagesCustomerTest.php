<?php

declare(strict_types=1);

use AIArmada\CashierChip\Exceptions\CustomerAlreadyCreated;
use AIArmada\CashierChip\Exceptions\InvalidCustomer;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('ManagesCustomer', function (): void {
    it('chip id', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertEquals('cli_123', $user->chipId());
    });

    it('has chip id', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertTrue($user->hasChipId());
    });

    it('has chip id false', function (): void {
        $user = $this->createUser(['email' => 'test@example.com']);

        $this->assertFalse($user->hasChipId());
    });

    it('chip name', function (): void {
        $user = $this->createUser(['name' => 'John Doe']);

        $this->assertEquals('John Doe', $user->chipName());
    });

    it('chip email', function (): void {
        $user = $this->createUser(['email' => 'john@example.com']);

        $this->assertEquals('john@example.com', $user->chipEmail());
    });

    it('chip phone', function (): void {
        $user = $this->createUser(['phone' => '+60123456789']);

        $this->assertEquals('+60123456789', $user->chipPhone());
    });

    it('chip country', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        // Default is 'MY'
        $this->assertEquals('MY', $user->chipCountry());
    });

    it('chip address', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        // Default is empty array
        $this->assertEquals([], $user->chipAddress());
    });

    it('preferred currency', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertEquals('MYR', $user->preferredCurrency());
    });

    it('balance returns formatted zero', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $balance = $user->balance();

        $this->assertIsString($balance);
    });

    it('raw balance returns zero', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertEquals(0, $user->rawBalance());
    });

    it('has balance', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        // rawBalance returns 0, so hasBalance is false
        $this->assertFalse($user->hasBalance());
    });

    it('has negative balance', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->hasNegativeBalance());
    });

    it('is not tax exempt', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertTrue($user->isNotTaxExempt());
    });

    it('is tax exempt', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->isTaxExempt());
    });

    it('reverse charge applies', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->assertFalse($user->reverseChargeApplies());
    });

    it('create as chip customer throws if exists', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $user->createAsChipCustomer();
    })->throws(CustomerAlreadyCreated::class);

    it('as chip customer throws if not exists', function (): void {
        $user = $this->createUser(['email' => 'test@example.com']);

        $user->asChipCustomer();
    })->throws(InvalidCustomer::class);

    it('update chip customer throws if not exists', function (): void {
        $user = $this->createUser(['email' => 'test@example.com']);

        $user->updateChipCustomer(['full_name' => 'New Name']);
    })->throws(InvalidCustomer::class);
});
