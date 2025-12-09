<?php

declare(strict_types=1);

namespace AIArmada\Stock\Facades;

use AIArmada\Stock\Services\StockReservationService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Stock\Models\StockReservation|null reserve(\Illuminate\Database\Eloquent\Model $stockable, int $quantity, string $cartId, int $ttlMinutes = 30)
 * @method static bool release(\Illuminate\Database\Eloquent\Model $stockable, string $cartId)
 * @method static int releaseAllForCart(string $cartId)
 * @method static array<\AIArmada\Stock\Models\StockTransaction> commitReservations(string $cartId, ?string $orderId = null)
 * @method static \AIArmada\Stock\Models\StockTransaction deductStock(\Illuminate\Database\Eloquent\Model $stockable, int $quantity, string $reason = 'sale', ?string $orderId = null)
 * @method static int getAvailableStock(\Illuminate\Database\Eloquent\Model $stockable)
 * @method static bool hasAvailableStock(\Illuminate\Database\Eloquent\Model $stockable, int $quantity = 1)
 * @method static int getReservedQuantity(\Illuminate\Database\Eloquent\Model $stockable)
 * @method static \AIArmada\Stock\Models\StockReservation|null getReservation(\Illuminate\Database\Eloquent\Model $stockable, string $cartId)
 * @method static int cleanupExpired()
 * @method static \AIArmada\Stock\Models\StockReservation|null extend(\Illuminate\Database\Eloquent\Model $stockable, string $cartId, int $minutes = 30)
 *
 * @see StockReservationService
 */
final class StockReservations extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StockReservationService::class;
    }
}
