<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class ConsoleCommandsTest extends CashierChipTestCase
{
    public function test_webhook_command_runs_successfully(): void
    {
        $this->artisan('cashier-chip:webhook')
            ->assertSuccessful();
    }

    public function test_webhook_command_outputs_webhook_url(): void
    {
        $this->artisan('cashier-chip:webhook')
            ->expectsOutputToContain('Webhook URL')
            ->assertSuccessful();
    }

    public function test_webhook_command_outputs_environment_variables(): void
    {
        $this->artisan('cashier-chip:webhook')
            ->expectsOutputToContain('Environment Variables')
            ->assertSuccessful();
    }
}
