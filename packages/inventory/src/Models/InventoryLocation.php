<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $name
 * @property string $code
 * @property string|null $address
 * @property bool $is_active
 * @property int $priority
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InventoryLevel> $inventoryLevels
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InventoryMovement> $movementsFrom
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InventoryMovement> $movementsTo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InventoryAllocation> $allocations
 * @property-read string|null $owner_display_name
 */
final class InventoryLocation extends Model
{
    /** @use HasFactory<\AIArmada\Inventory\Database\Factories\InventoryLocationFactory> */
    use HasFactory;

    use HasUuids;

    public const DEFAULT_LOCATION_CODE = 'DEFAULT';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'address',
        'is_active',
        'priority',
        'owner_type',
        'owner_id',
        'metadata',
    ];

    /**
     * Get or create the default location for simple setups.
     */
    public static function getOrCreateDefault(): self
    {
        return self::firstOrCreate(
            ['code' => self::DEFAULT_LOCATION_CODE],
            [
                'name' => 'Default Location',
                'is_active' => true,
                'priority' => 100,
            ]
        );
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.table_names.locations', 'inventory_locations');
    }

    /**
     * Get the owner model (polymorphic relationship).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get all inventory levels at this location.
     *
     * @return HasMany<InventoryLevel, $this>
     */
    public function inventoryLevels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class, 'location_id');
    }

    /**
     * Get movements originating from this location.
     *
     * @return HasMany<InventoryMovement, $this>
     */
    public function movementsFrom(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'from_location_id');
    }

    /**
     * Get movements arriving at this location.
     *
     * @return HasMany<InventoryMovement, $this>
     */
    public function movementsTo(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'to_location_id');
    }

    /**
     * Get all allocations at this location.
     *
     * @return HasMany<InventoryAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryAllocation::class, 'location_id');
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @param  EloquentModel|null  $owner  The owner to scope to
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true): Builder
    {
        if (! config('inventory.owner.enabled', false)) {
            return $query;
        }

        if (! $owner) {
            return $includeGlobal
                ? $query->whereNull('owner_id')
                : $query->whereNull('owner_type')->whereNull('owner_id');
        }

        return $query->where(function (Builder $builder) use ($owner, $includeGlobal): void {
            $builder->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (Builder $inner): void {
                    $inner->whereNull('owner_type')->whereNull('owner_id');
                });
            }
        });
    }

    /**
     * Scope query to only global (ownerless) records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeGlobalOnly(Builder $query): Builder
    {
        return $query->whereNull('owner_type')->whereNull('owner_id');
    }

    /**
     * Scope to only active locations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by priority (highest first).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority');
    }

    /**
     * Check if this model has an owner assigned.
     */
    public function hasOwner(): bool
    {
        return $this->owner_type !== null && $this->owner_id !== null;
    }

    /**
     * Check if this model is global (no owner).
     */
    public function isGlobal(): bool
    {
        return ! $this->hasOwner();
    }

    /**
     * Get the human-readable display name for the owner.
     */
    public function getOwnerDisplayNameAttribute(): ?string
    {
        $owner = $this->owner;

        if (! $owner) {
            return null;
        }

        if (method_exists($owner, 'getAttribute')) {
            /** @var string|null $name */
            $name = $owner->getAttribute('name');
            /** @var string|null $displayName */
            $displayName = $owner->getAttribute('display_name');
            /** @var string|null $email */
            $email = $owner->getAttribute('email');
            /** @var int|string $key */
            $key = $owner->getKey();

            return $name ?? $displayName ?? $email ?? class_basename($owner).':'.(string) $key;
        }

        /** @var int|string $key */
        $key = $owner->getKey();

        return class_basename($owner).':'.(string) $key;
    }

    /**
     * Check if this is the default location.
     */
    public function isDefault(): bool
    {
        return $this->code === self::DEFAULT_LOCATION_CODE;
    }

    /**
     * Handle model lifecycle events.
     */
    protected static function booted(): void
    {
        self::deleting(function (InventoryLocation $location): void {
            $location->inventoryLevels()->delete();
            $location->allocations()->delete();
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \AIArmada\Inventory\Database\Factories\InventoryLocationFactory
    {
        return \AIArmada\Inventory\Database\Factories\InventoryLocationFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'metadata' => 'array',
        ];
    }
}
