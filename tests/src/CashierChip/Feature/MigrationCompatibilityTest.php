<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\CashierChip\CashierChipWithStripeTestCase;
use Illuminate\Support\Facades\Schema;

uses(CashierChipWithStripeTestCase::class);

it('coexists with laravel cashier customer column migrations', function (): void {
    expect(Schema::hasColumns('users', [
        'stripe_id',
        'chip_id',
        'default_pm_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ]))->toBeTrue();

    expect(Schema::hasColumn('users', 'chip_default_payment_method'))->toBeFalse();
});