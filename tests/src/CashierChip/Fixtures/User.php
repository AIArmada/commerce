<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Fixtures;

use AIArmada\CashierChip\Billable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Billable;

    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];
}
