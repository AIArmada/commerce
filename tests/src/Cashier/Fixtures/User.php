<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Cashier\Fixtures;

use AIArmada\Cashier\Billable;
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
