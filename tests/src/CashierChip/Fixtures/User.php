<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Fixtures;

use AIArmada\CashierChip\Billing\Billable;
use AIArmada\CashierChip\Contracts\BillableContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements BillableContract
{
    use Billable;
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return UserFactory::new();
    }
}
