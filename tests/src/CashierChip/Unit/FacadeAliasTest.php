<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Billable;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Facades\CashierChip;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('FacadeAlias', function (): void {
    it('billing classes are loadable', function (): void {
        $this->assertTrue(class_exists(Cashier::class));
        $this->assertTrue(trait_exists(Billable::class));
    });

    it('cashier chip facade resolves billing cashier', function (): void {
        $this->assertInstanceOf(Cashier::class, CashierChip::getFacadeRoot());
    });
});
