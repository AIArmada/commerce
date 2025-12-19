<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentCashier\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class TenantRecord extends Model
{
    protected $table = 'filament_cashier_tenant_records';

    protected $fillable = [
        'user_id',
        'name',
    ];
}
