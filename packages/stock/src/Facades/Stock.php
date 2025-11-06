<?php

declare(strict_types=1);

namespace AIArmada\Stock\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Stock\Models\StockTransaction addStock(\Illuminate\Database\Eloquent\Model $model, int $quantity, string $reason = 'restock', ?string $note = null, ?string $userId = null)
 * @method static \AIArmada\Stock\Models\StockTransaction removeStock(\Illuminate\Database\Eloquent\Model $model, int $quantity, string $reason = 'adjustment', ?string $note = null, ?string $userId = null)
 * @method static \AIArmada\Stock\Models\StockTransaction|null adjustStock(\Illuminate\Database\Eloquent\Model $model, int $currentStock, int $actualStock, ?string $note = null, ?string $userId = null)
 * @method static int getCurrentStock(\Illuminate\Database\Eloquent\Model $model)
 * @method static \Illuminate\Database\Eloquent\Collection getStockHistory(\Illuminate\Database\Eloquent\Model $model, int $limit = 50)
 * @method static bool hasStock(\Illuminate\Database\Eloquent\Model $model, int $quantity = 1)
 * @method static bool isLowStock(\Illuminate\Database\Eloquent\Model $model, ?int $threshold = null)
 *
 * @see \AIArmada\Stock\Services\StockService
 */
final class Stock extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AIArmada\Stock\Services\StockService::class;
    }
}
