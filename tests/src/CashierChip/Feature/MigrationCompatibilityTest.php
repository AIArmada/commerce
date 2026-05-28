<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\CashierChip\CashierChipWithStripeTestCase;
use Illuminate\Support\Facades\Schema;

uses(CashierChipWithStripeTestCase::class);

it('coexists with laravel cashier customer column migrations', function (): void {
    expect(Schema::hasColumns('users', [
        'stripe_id',
        'trial_ends_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('cashier_chip_payment_methods', [
        'billable_type',
        'billable_id',
        'recurring_token',
    ]))->toBeTrue();

    expect(Schema::hasColumns('chip_customers', [
        'subject_type',
        'subject_id',
        'chip_customer_id',
    ]))->toBeTrue();
});
