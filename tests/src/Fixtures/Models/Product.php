<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Fixtures\Models;

use AIArmada\Stock\Contracts\StockableInterface;
use AIArmada\Stock\Traits\HasStock;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements StockableInterface
{
    use HasStock;
    use HasUuids;

    protected $table = 'test_products';

    protected $fillable = ['name', 'sku', 'price'];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
