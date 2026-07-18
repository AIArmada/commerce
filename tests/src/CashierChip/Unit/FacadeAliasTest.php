<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Billing\Billable;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Facades\CashierChip;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

final class FacadeAliasTest extends CashierChipTestCase
{
    public function test_billing_classes_are_loadable(): void
    {
        $this->assertTrue(class_exists(Cashier::class));
        $this->assertTrue(trait_exists(Billable::class));
    }

    public function test_cashier_chip_facade_resolves_billing_cashier(): void
    {
        $this->assertInstanceOf(Cashier::class, CashierChip::getFacadeRoot());
    }
}
