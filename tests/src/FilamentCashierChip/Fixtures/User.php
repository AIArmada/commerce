<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentCashierChip\Fixtures;

use AIArmada\CashierChip\Billing\Billable;
use AIArmada\CashierChip\Contracts\BillableContract;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements BillableContract
{
    use Billable;

    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];
}
