<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Inventory\Database\Factories\InventoryAllocationFactory;
use AIArmada\Inventory\Features\Stock\Api\StockIdentity;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Traceability\Api\BatchIdentity;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string $location_id
 * @property string $level_id
 * @property string|null $batch_id
 * @property string|null $reservation_group_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property string $cart_id
 * @property int $quantity
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read InventoryLocation $location
 * @property-read InventoryLevel $level
 * @property-read Model $inventoryable
 * @property-read InventoryReservation|null $reservationGroup
 */
final class InventoryAllocation extends Model implements Auditable
{
    use HasCommerceAudit;

    /** @use HasFactory<InventoryAllocationFactory> */
    use HasFactory;

    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'inventory.owner';

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
        'batch_id',
        'reservation_group_id',
        'cart_id',
        'quantity',
        'expires_at',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.database.tables.allocations', 'inventory_allocations');
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
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    /**
     * @return BelongsTo<InventoryReservation, $this>
     */
    public function reservationGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class, 'reservation_group_id');
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
     * Scope to filter by cart ID.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForCart(Builder $query, string $cartId): Builder
    {
        return $query->where('cart_id', $cartId);
    }

    /**
     * Scope to filter active (non-expired) allocations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', CarbonImmutable::now());
    }

    /**
     * Scope to filter expired allocations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', CarbonImmutable::now());
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
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): InventoryAllocationFactory
    {
        return InventoryAllocationFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (InventoryAllocation $allocation): void {
            if ($allocation->quantity <= 0) {
                throw new InvalidArgumentException('Allocation quantity must be positive.');
            }

            if (! app(StockIdentity::class)->matchesWritableLevel(
                $allocation->level_id,
                $allocation->inventoryable_type,
                $allocation->inventoryable_id,
                $allocation->location_id,
            )) {
                throw new AuthorizationException('Inventory allocation does not match a writable stock level.');
            }

            if ($allocation->batch_id !== null && ! app(BatchIdentity::class)->matchesWritableBatch(
                $allocation->batch_id,
                $allocation->inventoryable_type,
                $allocation->inventoryable_id,
                $allocation->location_id,
            )) {
                throw new AuthorizationException('Inventory allocation does not match a writable batch.');
            }

            if (! InventoryOwnerScope::isEnabled()) {
                return;
            }

            $owner = InventoryOwnerScope::resolveOwner();
            $ownerType = $owner?->getMorphClass();
            $ownerId = $owner?->getKey();

            if (($allocation->owner_type !== null || $allocation->owner_id !== null)
                && ($allocation->owner_type !== $ownerType || (string) $allocation->owner_id !== (string) $ownerId)) {
                throw new AuthorizationException('Owner mismatch between inventory allocation and location.');
            }

            $allocation->owner_type = $ownerType;
            $allocation->owner_id = $ownerId;
        });
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
            'expires_at' => 'immutable_datetime',
        ];
    }
}
