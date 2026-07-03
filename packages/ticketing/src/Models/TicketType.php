<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Models;

use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Ticketing\Database\Factories\TicketTypeFactory;
use AIArmada\Ticketing\Enums\PricingMode;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property string $ticketable_type
 * @property string $ticketable_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string $access_type
 * @property SeatingMode|null $seating_mode
 * @property int|null $price
 * @property string|null $currency
 * @property int $admits_quantity
 * @property int|null $min_quantity
 * @property int|null $max_quantity
 * @property CarbonImmutable|null $sales_starts_at
 * @property CarbonImmutable|null $sales_ends_at
 * @property string $status
 * @property string $visibility
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $ticketable
 * @property-read Collection<int, TicketTypeComponent> $components
 * @property-read Collection<int, TicketTypeComponent> $childComponents
 * @property-read Collection<int, TicketTypeProduct> $bundleProducts
 * @property-read Collection<int, TicketTypeProduct> $requiredBundleProducts
 * @property-read Collection<int, TicketTypeProduct> $optionalBundleProducts
 * @property-read Collection<int, TicketTypeSeatingOption> $seatingOptions
 * @property-read Collection<int, Pass> $passes
 * @property-read Collection<int, InventoryLevel> $inventoryLevels
 * @property-read Collection<int, InventoryMovement> $inventoryMovements
 * @property-read Collection<int, InventoryAllocation> $inventoryAllocations
 */
class TicketType extends Model
{
    use HasFactory;
    use HasUuids;

    protected static function newFactory(): TicketTypeFactory
    {
        return TicketTypeFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'admits_quantity' => 1,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'ticketable_type',
        'ticketable_id',
        'name',
        'code',
        'description',
        'access_type',
        'seating_mode',
        'price',
        'currency',
        'admits_quantity',
        'min_quantity',
        'max_quantity',
        'sales_starts_at',
        'sales_ends_at',
        'status',
        'visibility',
        'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('ticketing.database.tables.ticket_types', 'ticket_types');
    }

    protected function casts(): array
    {
        return [
            'seating_mode' => SeatingMode::class,
            'price' => 'integer',
            'admits_quantity' => 'integer',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'sort_order' => 'integer',
            'sales_starts_at' => 'immutable_datetime',
            'sales_ends_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function ticketable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<Pass, $this>
     */
    public function passes(): HasMany
    {
        return $this->hasMany(Pass::class);
    }

    /**
     * @return HasMany<TicketTypeComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(TicketTypeComponent::class, 'parent_ticket_type_id');
    }

    /**
     * @return HasMany<TicketTypeComponent, $this>
     */
    public function childComponents(): HasMany
    {
        return $this->hasMany(TicketTypeComponent::class, 'component_ticket_type_id');
    }

    /**
     * @return HasMany<TicketTypeProduct, $this>
     */
    public function bundleProducts(): HasMany
    {
        return $this->hasMany(TicketTypeProduct::class);
    }

    /**
     * @return HasMany<TicketTypeProduct, $this>
     */
    public function requiredBundleProducts(): HasMany
    {
        return $this->bundleProducts()->where('inclusion_mode', 'required');
    }

    /**
     * @return HasMany<TicketTypeProduct, $this>
     */
    public function optionalBundleProducts(): HasMany
    {
        return $this->bundleProducts()->where('inclusion_mode', 'optional');
    }

    /**
     * @return HasMany<TicketTypeSeatingOption, $this>
     */
    public function seatingOptions(): HasMany
    {
        return $this->hasMany(TicketTypeSeatingOption::class);
    }

    /**
     * @return MorphMany<InventoryLevel, $this>
     */
    public function inventoryLevels(): MorphMany
    {
        $class = 'AIArmada\Inventory\Models\InventoryLevel';

        if (! class_exists($class)) {
            /** @var MorphMany<InventoryLevel, $this> */
            return $this->newMorphMany(
                $this->newRelatedInstance(Model::class)->newQuery(),
                $this,
                'inventoryable_type',
                'inventoryable_id',
                'id'
            );
        }

        return $this->morphMany($class, 'inventoryable');
    }

    /**
     * @return MorphMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): MorphMany
    {
        $class = 'AIArmada\Inventory\Models\InventoryMovement';

        if (! class_exists($class)) {
            /** @var MorphMany<InventoryMovement, $this> */
            return $this->newMorphMany(
                $this->newRelatedInstance(Model::class)->newQuery(),
                $this,
                'inventoryable_type',
                'inventoryable_id',
                'id'
            );
        }

        return $this->morphMany($class, 'inventoryable');
    }

    /**
     * @return MorphMany<InventoryAllocation, $this>
     */
    public function inventoryAllocations(): MorphMany
    {
        $class = 'AIArmada\Inventory\Models\InventoryAllocation';

        if (! class_exists($class)) {
            /** @var MorphMany<InventoryAllocation, $this> */
            return $this->newMorphMany(
                $this->newRelatedInstance(Model::class)->newQuery(),
                $this,
                'inventoryable_type',
                'inventoryable_id',
                'id'
            );
        }

        return $this->morphMany($class, 'inventoryable');
    }

    public function getTotalOnHand(): int
    {
        if (! class_exists('AIArmada\Inventory\Models\InventoryLevel')) {
            return 0;
        }

        return (int) $this->inventoryLevels()->sum('quantity_on_hand');
    }

    public function getTotalAvailable(): int
    {
        if (! class_exists('AIArmada\Inventory\Models\InventoryLevel')) {
            return 0;
        }

        return (int) $this->inventoryLevels()
            ->get()
            ->sum(static fn (InventoryLevel $level): int => $level->available);
    }

    public function hasInventory(int $quantity): bool
    {
        return $this->getTotalAvailable() >= $quantity;
    }

    public function effectivePricingMode(): PricingMode
    {
        if ($this->price === null) {
            return PricingMode::Free;
        }

        if ($this->price > 0) {
            return PricingMode::Paid;
        }

        return PricingMode::Free;
    }

    public function getKey(): string
    {
        return $this->id;
    }
}
