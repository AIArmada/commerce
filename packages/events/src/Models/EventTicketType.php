<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventTicketTypeFactory;
use AIArmada\Events\Enums\BundleInclusionMode;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use AIArmada\Inventory\Contracts\InventoryableInterface;
use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string $access_type
 * @property string|null $seating_mode
 * @property int $price
 * @property string $currency
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
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 * @property-read Collection<int, EventTicketTypeProduct> $bundleProducts
 * @property-read Collection<int, EventTicketTypeProduct> $requiredBundleProducts
 * @property-read Collection<int, EventTicketTypeProduct> $optionalBundleProducts
 * @property-read Collection<int, InventoryLevel> $inventoryLevels
 * @property-read Collection<int, InventoryMovement> $inventoryMovements
 * @property-read Collection<int, InventoryAllocation> $inventoryAllocations
 */
final class EventTicketType extends Model implements InventoryableInterface
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'name', 'code', 'description',
        'access_type', 'seating_mode',
        'price', 'currency',
        'admits_quantity', 'min_quantity', 'max_quantity',
        'sales_starts_at', 'sales_ends_at',
        'status', 'visibility', 'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_ticket_types', 'event_ticket_types');
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'admits_quantity' => 'integer',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'sales_starts_at' => 'immutable_datetime',
            'sales_ends_at' => 'immutable_datetime',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventRegistrationItem, $this>
     */
    public function registrationItems(): HasMany
    {
        return $this->hasMany(EventRegistrationItem::class, 'event_ticket_type_id');
    }

    /**
     * @return HasMany<EventPass, $this>
     */
    public function passes(): HasMany
    {
        return $this->hasMany(EventPass::class, 'event_ticket_type_id');
    }

    /**
     * @return HasMany<EventTicketTypeComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(EventTicketTypeComponent::class, 'parent_ticket_type_id');
    }

    /**
     * @return HasMany<EventTicketTypeSeatingOption, $this>
     */
    public function seatingOptions(): HasMany
    {
        return $this->hasMany(EventTicketTypeSeatingOption::class, 'event_ticket_type_id');
    }

    /**
     * @return HasMany<EventTicketTypeProduct, $this>
     */
    public function bundleProducts(): HasMany
    {
        return $this->hasMany(EventTicketTypeProduct::class, 'event_ticket_type_id');
    }

    /**
     * @return HasMany<EventTicketTypeProduct, $this>
     */
    public function requiredBundleProducts(): HasMany
    {
        return $this->bundleProducts()
            ->where('inclusion_mode', BundleInclusionMode::Required->value);
    }

    /**
     * @return HasMany<EventTicketTypeProduct, $this>
     */
    public function optionalBundleProducts(): HasMany
    {
        return $this->bundleProducts()
            ->where('inclusion_mode', BundleInclusionMode::Optional->value);
    }

    /**
     * @return HasMany<EventTicketTypeComponent, $this>
     */
    public function childComponents(): HasMany
    {
        return $this->hasMany(EventTicketTypeComponent::class, 'component_ticket_type_id');
    }

    /**
     * @return MorphMany<InventoryLevel, $this>
     */
    public function inventoryLevels(): MorphMany
    {
        return $this->morphMany(InventoryLevel::class, 'inventoryable');
    }

    /**
     * @return MorphMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'inventoryable');
    }

    /**
     * @return MorphMany<InventoryAllocation, $this>
     */
    public function inventoryAllocations(): MorphMany
    {
        return $this->morphMany(InventoryAllocation::class, 'inventoryable');
    }

    public function getTotalOnHand(): int
    {
        return (int) $this->inventoryLevels()->sum('quantity_on_hand');
    }

    public function getTotalAvailable(): int
    {
        return $this->inventoryLevels()
            ->get()
            ->sum(fn (InventoryLevel $level): int => $level->available);
    }

    public function hasInventory(int $quantity): bool
    {
        return $this->getTotalAvailable() >= $quantity;
    }

    public function getInventoryAtLocation(string $locationId): ?InventoryLevel
    {
        return $this->inventoryLevels()
            ->where('location_id', $locationId)
            ->first();
    }

    public function getAllocationStrategy(): ?AllocationStrategy
    {
        return null;
    }

    public function receive(
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null,
    ): InventoryMovement {
        $service = app(InventoryService::class);

        return $service->receive($this, $locationId, $quantity, $reason, $note, $userId);
    }

    public function ship(
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null,
    ): InventoryMovement {
        $service = app(InventoryService::class);

        return $service->ship($this, $locationId, $quantity, $reason, $reference, $note, $userId);
    }

    public function transfer(
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        ?string $note = null,
        ?string $userId = null,
    ): InventoryMovement {
        $service = app(InventoryService::class);

        return $service->transfer($this, $fromLocationId, $toLocationId, $quantity, $note, $userId);
    }

    /**
     * @return Collection<int, InventoryAllocation>
     */
    public function allocate(int $quantity, string $cartId, int $ttlMinutes = 30): Collection
    {
        $service = app(InventoryAllocationService::class);

        return $service->allocate($this, $quantity, $cartId, $ttlMinutes);
    }

    public function release(string $cartId): int
    {
        $service = app(InventoryAllocationService::class);

        return $service->release($this, $cartId);
    }

    protected static function newFactory(): EventTicketTypeFactory
    {
        return EventTicketTypeFactory::new();
    }
}
