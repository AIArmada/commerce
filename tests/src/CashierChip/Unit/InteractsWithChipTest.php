<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Concerns\InteractsWithChip;
use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('InteractsWithChip', function (): void {
    it('chip returns service', function (): void {
        // Create a test class that uses the trait
        $testClass = new class
        {
            use InteractsWithChip;
        };

        // Since Cashier::fake() is already called in setUp(), this should return FakeChipCollectService
        $service = $testClass::chip();

        $this->assertInstanceOf(FakeChipCollectService::class, $service);
    });

    it('chip returns same instance', function (): void {
        $testClass = new class
        {
            use InteractsWithChip;
        };

        $service1 = $testClass::chip();
        $service2 = $testClass::chip();

        $this->assertSame($service1, $service2);
    });
});
