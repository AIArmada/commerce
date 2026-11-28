<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Inventory\Fixtures;

use AIArmada\Inventory\Contracts\InventoryableInterface;
use AIArmada\Inventory\Features\Traceability\Traits\HasSerialNumbers;
use AIArmada\Inventory\Traits\HasInventory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SerializedInventoryItem extends Model implements InventoryableInterface
{
    use HasInventory;
    use HasSerialNumbers;
    use HasUuids;

    protected $table = 'inventory_test_products';

    protected $fillable = ['name'];
}
