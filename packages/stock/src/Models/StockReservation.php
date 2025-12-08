<?php

declare(strict_types=1);

namespace AIArmada\Stock\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Represents temporary stock reservations for cart items.
 *
 * Reservations hold stock while customers are checking out,
 * preventing overselling. They expire automatically after TTL.
 *
 * @property string $id
 * @property string $stockable_type
 * @property string $stockable_id
 * @property string $cart_id
 * @property int $quantity
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class StockReservation extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'stockable_type',
        'stockable_id',
        'cart_id',
        'quantity',
        'expires_at',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        $database = config('stock.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'stock_';
        $tables = $database['tables'] ?? [];

        return $tables['reservations'] ?? config('stock.reservations_table', $tablePrefix.'reservations');
    }

    /**
     * Get the stockable model (Product, Variant, etc.)
     */
    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if reservation is still valid.
     */
    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * Check if reservation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Extend the reservation.
     */
    public function extend(int $minutes = 30): self
    {
        $this->expires_at = now()->addMinutes($minutes);
        $this->save();

        return $this;
    }

    /**
     * Scope: Only active (non-expired) reservations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope: Only expired reservations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope: By cart ID.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForCart($query, string $cartId)
    {
        return $query->where('cart_id', $cartId);
    }

    /**
     * Scope: For a specific stockable.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForStockable($query, Model $model)
    {
        return $query
            ->where('stockable_type', $model->getMorphClass())
            ->where('stockable_id', $model->getKey());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'quantity' => 'integer',
        ];
    }
}
