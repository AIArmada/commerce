<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string $serial_number
 * @property string|null $sku
 * @property string|null $location_id
 * @property string|null $batch_id
 * @property string $status
 * @property string $condition
 * @property int|null $unit_cost_minor
 * @property string $currency
 * @property Carbon|null $warranty_expires_at
 * @property Carbon|null $manufactured_at
 * @property Carbon|null $received_at
 * @property string|null $assigned_to_type
 * @property string|null $assigned_to_id
 * @property Carbon|null $assigned_at
 * @property string|null $order_id
 * @property Carbon|null $sold_at
 * @property string|null $customer_id
 * @property string|null $supplier_id
 * @property string|null $purchase_order_number
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read bool $is_warranty_active
 * @property-read int|null $days_until_warranty_expires
 * @property-read InventoryLocation|null $location
 * @property-read InventoryBatch|null $batch
 * @property-read Model $inventoryable
 * @property-read Model|null $assignedTo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InventorySerialHistory> $history
 */
final class InventorySerial extends Model
{
    /** @use HasFactory<\AIArmada\Inventory\Database\Factories\InventorySerialFactory> */
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
        'serial_number',
        'sku',
        'location_id',
        'batch_id',
        'status',
        'condition',
        'unit_cost_minor',
        'currency',
        'warranty_expires_at',
        'manufactured_at',
        'received_at',
        'assigned_to_type',
        'assigned_to_id',
        'assigned_at',
        'order_id',
        'sold_at',
        'customer_id',
        'supplier_id',
        'purchase_order_number',
        'notes',
        'metadata',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.table_names.serials', 'inventory_serials');
    }

    /**
     * Get the inventoryable model.
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the location.
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * Get the batch.
     *
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    /**
     * Get the assigned to model.
     */
    public function assignedTo(): MorphTo
    {
        return $this->morphTo('assigned_to');
    }

    /**
     * Get the history.
     *
     * @return HasMany<InventorySerialHistory, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(InventorySerialHistory::class, 'serial_id')
            ->orderByDesc('occurred_at');
    }

    /**
     * Get the status as enum.
     */
    public function getStatusEnum(): SerialStatus
    {
        return SerialStatus::from($this->status);
    }

    /**
     * Get the condition as enum.
     */
    public function getConditionEnum(): SerialCondition
    {
        return SerialCondition::from($this->condition);
    }

    /**
     * Check if warranty is active.
     */
    public function getIsWarrantyActiveAttribute(): bool
    {
        if ($this->warranty_expires_at === null) {
            return false;
        }

        return $this->warranty_expires_at->isFuture();
    }

    /**
     * Get days until warranty expires.
     */
    public function getDaysUntilWarrantyExpiresAttribute(): ?int
    {
        if ($this->warranty_expires_at === null) {
            return null;
        }

        return (int) now()->diffInDays($this->warranty_expires_at, false);
    }

    /**
     * Check if serial is under warranty (method form for Filament compatibility).
     */
    public function isUnderWarranty(): bool
    {
        return $this->is_warranty_active;
    }

    /**
     * Get warranty days remaining (method form for Filament compatibility).
     */
    public function warrantyDaysRemaining(): ?int
    {
        return $this->days_until_warranty_expires;
    }

    /**
     * Check if serial is available for sale.
     */
    public function isAvailable(): bool
    {
        return $this->getStatusEnum()->isAllocatable()
            && $this->getConditionEnum()->isSellable();
    }

    /**
     * Check if transition to new status is allowed.
     */
    public function canTransitionTo(SerialStatus $newStatus): bool
    {
        return $this->getStatusEnum()->canTransitionTo($newStatus);
    }

    /**
     * Update status with validation.
     */
    public function transitionTo(SerialStatus $newStatus): self
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot transition from %s to %s',
                    $this->getStatusEnum()->label(),
                    $newStatus->label()
                )
            );
        }

        $this->update(['status' => $newStatus->value]);

        return $this;
    }

    /**
     * Scope to filter by status.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, SerialStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * Scope to filter available serials.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', SerialStatus::Available->value);
    }

    /**
     * Scope to filter in stock serials.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SerialStatus::Available->value,
            SerialStatus::Reserved->value,
        ]);
    }

    /**
     * Scope to filter by location.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAtLocation(Builder $query, string $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }

    /**
     * Scope to filter by condition.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithCondition(Builder $query, SerialCondition $condition): Builder
    {
        return $query->where('condition', $condition->value);
    }

    /**
     * Scope to filter sellable serials.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('status', SerialStatus::Available->value)
            ->whereIn('condition', [
                SerialCondition::New->value,
                SerialCondition::LikeNew->value,
                SerialCondition::Refurbished->value,
                SerialCondition::Used->value,
            ]);
    }

    /**
     * Scope to filter by warranty status.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnderWarranty(Builder $query): Builder
    {
        return $query->where('warranty_expires_at', '>', now());
    }

    /**
     * Scope to filter warranty expiring soon.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWarrantyExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('warranty_expires_at')
            ->whereBetween('warranty_expires_at', [now(), now()->addDays($days)]);
    }

    /**
     * Handle model lifecycle events.
     */
    protected static function booted(): void
    {
        self::deleting(function (InventorySerial $serial): void {
            $serial->history()->delete();
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \AIArmada\Inventory\Database\Factories\InventorySerialFactory
    {
        return \AIArmada\Inventory\Database\Factories\InventorySerialFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_cost_minor' => 'integer',
            'warranty_expires_at' => 'date',
            'manufactured_at' => 'date',
            'received_at' => 'date',
            'assigned_at' => 'datetime',
            'sold_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
