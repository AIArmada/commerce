<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentCashierChip\Fixtures;

use AIArmada\CashierChip\Billing\Billable;
use AIArmada\CashierChip\Billing\BillableModel;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements BillableModel
{
    use Billable;

    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];
}
