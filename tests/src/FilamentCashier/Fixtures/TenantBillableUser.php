<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentCashier\Fixtures;

use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;

final class TenantBillableUser extends Model
{
    use HasOwner;

    protected $table = 'filament_cashier_tenant_billables';

    protected $fillable = [
        'name',
        'email',
        'owner_type',
        'owner_id',
    ];
}
