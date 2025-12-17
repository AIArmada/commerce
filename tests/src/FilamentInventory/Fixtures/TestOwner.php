<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentInventory\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class TestOwner extends Model
{
    use HasUuids;

    protected $table = 'filament_inventory_test_owners';

    protected $fillable = [
        'name',
    ];
}
