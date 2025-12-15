<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Concerns\InteractsWithChip;
use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class InteractsWithChipTest extends CashierChipTestCase
{
    public function test_chip_returns_service(): void
    {
        // Create a test class that uses the trait
        $testClass = new class
        {
            use InteractsWithChip;
        };

        // Since Cashier::fake() is already called in setUp(), this should return FakeChipCollectService
        $service = $testClass::chip();

        $this->assertInstanceOf(FakeChipCollectService::class, $service);
    }

    public function test_chip_returns_same_instance(): void
    {
        $testClass = new class
        {
            use InteractsWithChip;
        };

        $service1 = $testClass::chip();
        $service2 = $testClass::chip();

        $this->assertSame($service1, $service2);
    }
}
