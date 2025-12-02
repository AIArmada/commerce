<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\Inventory\Enums\AllocationStrategy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string $location_id
 * @property int $quantity_on_hand
 * @property int $quantity_reserved
 * @property int|null $reorder_point
 * @property string|null $allocation_strategy
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read int $available
 * @property-read InventoryLocation $location
 * @property-read Model $inventoryable
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InventoryAllocation> $allocations
 */
final class InventoryLevel extends Model
{
    /** @use HasFactory<\AIArmada\Inventory\Database\Factories\InventoryLevelFactory> */
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
        'quantity_on_hand',
        'quantity_reserved',
        'reorder_point',
        'allocation_strategy',
        'metadata',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.table_names.levels', 'inventory_levels');
    }

    /**
     * Get the location for this inventory level.
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * Get the inventoryable model (Product, Variant, etc.)
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get allocations for this inventory level.
     *
     * @return HasMany<InventoryAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryAllocation::class, 'level_id');
    }

    /**
     * Get the available quantity (on_hand - reserved).
     */
    public function getAvailableAttribute(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved);
    }

    /**
     * Get the available quantity (explicit method for Filament compatibility).
     */
    public function getAvailableQuantity(): int
    {
        return $this->available;
    }

    /**
     * Check if inventory is low (below reorder point).
     */
    public function isLowStock(?int $threshold = null): bool
    {
        $threshold ??= $this->reorder_point ?? config('inventory.default_reorder_point', 10);

        return $this->available <= $threshold;
    }

    /**
     * Check if available quantity is sufficient.
     */
    public function hasAvailable(int $quantity): bool
    {
        return $this->available >= $quantity;
    }

    /**
     * Get the effective allocation strategy (own or global).
     */
    public function getEffectiveAllocationStrategy(): AllocationStrategy
    {
        if ($this->allocation_strategy !== null) {
            return AllocationStrategy::from($this->allocation_strategy);
        }

        $global = config('inventory.allocation_strategy', 'priority');

        return AllocationStrategy::from($global);
    }

    /**
     * Increment on-hand quantity.
     */
    public function incrementOnHand(int $quantity): self
    {
        $this->increment('quantity_on_hand', $quantity);

        return $this;
    }

    /**
     * Decrement on-hand quantity.
     */
    public function decrementOnHand(int $quantity): self
    {
        $this->decrement('quantity_on_hand', $quantity);

        return $this;
    }

    /**
     * Increment reserved quantity.
     */
    public function incrementReserved(int $quantity): self
    {
        $this->increment('quantity_reserved', $quantity);

        return $this;
    }

    /**
     * Decrement reserved quantity.
     */
    public function decrementReserved(int $quantity): self
    {
        $this->decrement('quantity_reserved', $quantity);

        return $this;
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
     * Scope to filter low stock items.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeLowStock(\Illuminate\Database\Eloquent\Builder $query, ?int $threshold = null): \Illuminate\Database\Eloquent\Builder
    {
        $threshold ??= config('inventory.default_reorder_point', 10);

        return $query->whereRaw('(quantity_on_hand - quantity_reserved) <= ?', [$threshold]);
    }

    /**
     * Scope to filter items with available stock.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeWithAvailable(\Illuminate\Database\Eloquent\Builder $query, int $minQuantity = 1): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereRaw('(quantity_on_hand - quantity_reserved) >= ?', [$minQuantity]);
    }

    /**
     * Handle model lifecycle events.
     */
    protected static function booted(): void
    {
        self::deleting(function (InventoryLevel $level): void {
            $level->allocations()->delete();
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \AIArmada\Inventory\Database\Factories\InventoryLevelFactory
    {
        return \AIArmada\Inventory\Database\Factories\InventoryLevelFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'reorder_point' => 'integer',
            'metadata' => 'array',
        ];
    }
}
