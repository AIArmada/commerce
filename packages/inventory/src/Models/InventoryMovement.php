<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\Inventory\Enums\MovementType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string|null $from_location_id
 * @property string|null $to_location_id
 * @property int $quantity
 * @property string $type
 * @property string|null $reason
 * @property string|null $reference
 * @property string|null $user_id
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read InventoryLocation|null $fromLocation
 * @property-read InventoryLocation|null $toLocation
 * @property-read Model $inventoryable
 * @property-read \Illuminate\Foundation\Auth\User|null $user
 */
final class InventoryMovement extends Model
{
    /** @use HasFactory<\AIArmada\Inventory\Database\Factories\InventoryMovementFactory> */
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
        'from_location_id',
        'to_location_id',
        'quantity',
        'type',
        'reason',
        'reference',
        'user_id',
        'note',
        'occurred_at',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.table_names.movements', 'inventory_movements');
    }

    /**
     * Get the inventoryable model (Product, Variant, etc.)
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the source location (for transfers and shipments).
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    /**
     * Get the destination location (for transfers and receipts).
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    /**
     * Get the user who performed the movement.
     *
     * @return BelongsTo<\Illuminate\Foundation\Auth\User, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<\Illuminate\Foundation\Auth\User> $userModel */
        $userModel = config('auth.providers.users.model');

        return $this->belongsTo($userModel);
    }

    /**
     * Get the movement type as enum.
     */
    public function getMovementType(): MovementType
    {
        return MovementType::from($this->type);
    }

    /**
     * Check if this is a receipt.
     */
    public function isReceipt(): bool
    {
        return $this->type === MovementType::Receipt->value;
    }

    /**
     * Check if this is a shipment.
     */
    public function isShipment(): bool
    {
        return $this->type === MovementType::Shipment->value;
    }

    /**
     * Check if this is a transfer.
     */
    public function isTransfer(): bool
    {
        return $this->type === MovementType::Transfer->value;
    }

    /**
     * Check if this is an adjustment.
     */
    public function isAdjustment(): bool
    {
        return $this->type === MovementType::Adjustment->value;
    }

    /**
     * Scope to filter by movement type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeOfType(\Illuminate\Database\Eloquent\Builder $query, MovementType $type): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * Scope to filter by reference.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeForReference(\Illuminate\Database\Eloquent\Builder $query, string $reference): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('reference', $reference);
    }

    /**
     * Scope to filter by location (from or to).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeAtLocation(\Illuminate\Database\Eloquent\Builder $query, string $locationId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) use ($locationId): void {
            $q->where('from_location_id', $locationId)
                ->orWhere('to_location_id', $locationId);
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \AIArmada\Inventory\Database\Factories\InventoryMovementFactory
    {
        return \AIArmada\Inventory\Database\Factories\InventoryMovementFactory::new();
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
            'occurred_at' => 'datetime',
        ];
    }
}
