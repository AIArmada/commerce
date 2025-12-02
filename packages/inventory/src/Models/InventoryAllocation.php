<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string $location_id
 * @property string $level_id
 * @property string $cart_id
 * @property int $quantity
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read InventoryLocation $location
 * @property-read InventoryLevel $level
 * @property-read Model $inventoryable
 */
final class InventoryAllocation extends Model
{
    /** @use HasFactory<\AIArmada\Inventory\Database\Factories\InventoryAllocationFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'location_id',
        'level_id',
        'cart_id',
        'quantity',
        'expires_at',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.table_names.allocations', 'inventory_allocations');
    }

    /**
     * Get the inventoryable model (Product, Variant, etc.)
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the location for this allocation.
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * Get the inventory level for this allocation.
     *
     * @return BelongsTo<InventoryLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(InventoryLevel::class, 'level_id');
    }

    /**
     * Check if the allocation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the allocation is still active (not expired).
     */
    public function isActive(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Extend the allocation expiry.
     */
    public function extend(int $minutes): self
    {
        $this->update([
            'expires_at' => now()->addMinutes($minutes),
        ]);

        return $this;
    }

    /**
     * Scope to filter by cart ID.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeForCart(\Illuminate\Database\Eloquent\Builder $query, string $cartId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('cart_id', $cartId);
    }

    /**
     * Scope to filter active (non-expired) allocations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to filter expired allocations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeExpired(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to filter by location.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeAtLocation(\Illuminate\Database\Eloquent\Builder $query, string $locationId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('location_id', $locationId);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \AIArmada\Inventory\Database\Factories\InventoryAllocationFactory
    {
        return \AIArmada\Inventory\Database\Factories\InventoryAllocationFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
