<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('ConsoleCommands', function (): void {
    it('webhook command runs successfully', function (): void {
        $this->artisan('cashier-chip:webhook')
            ->assertSuccessful();
    });

    it('webhook command outputs webhook url', function (): void {
        $this->artisan('cashier-chip:webhook')
            ->expectsOutputToContain('Webhook URL')
            ->assertSuccessful();
    });

    it('webhook command outputs environment variables', function (): void {
        $this->artisan('cashier-chip:webhook')
            ->expectsOutputToContain('Environment Variables')
            ->assertSuccessful();
    });

    it('webhook command outputs supported events', function (): void {
        $this->artisan('cashier-chip:webhook')
            ->expectsOutputToContain('purchase.paid')
            ->expectsOutputToContain('purchase.payment_failure')
            ->assertSuccessful();
    });
});
