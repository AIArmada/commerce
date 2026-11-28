<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Services;

use AIArmada\Inventory\Features\Stock\Actions\AdjustInventory;
use AIArmada\Inventory\Features\Stock\Actions\BootstrapInventory;
use AIArmada\Inventory\Features\Stock\Actions\ReceiveInventory;
use AIArmada\Inventory\Features\Stock\Actions\ReceiveAtDefault;
use AIArmada\Inventory\Features\Stock\Actions\ShipFromDefault;
use AIArmada\Inventory\Features\Stock\Actions\ShipInventory;
use AIArmada\Inventory\Features\Stock\Actions\TransferInventory;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Stock\Persistence\InventoryLevelStore;
use AIArmada\Inventory\Features\Stock\Queries\InventoryStockQuery;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Core inventory operations service.
 *
 * Handles receiving, shipping, transferring, and adjusting inventory
 * with full movement tracking and event dispatching.
 *
 * @example Receiving inventory
 * ```php
 * $service = app(InventoryService::class);
 * $movement = $service->receive($product, $location->id, 100, reason: 'PO-2024-001');
 * ```
 * @example Shipping inventory
 * ```php
 * $movement = $service->ship($product, $location->id, 10, reason: 'ORD-2024-001');
 * ```
 * @example Checking availability
 * ```php
 * $availability = $service->getAvailability($product);
 * // ['location-uuid' => 80]
 * ```
 */
final class InventoryService
{
    /**
     * Receive inventory at a location.
     *
     * Creates an inventory level record if one doesn't exist, increments
     * on-hand quantity, and records the movement for audit purposes.
     *
     * @param  Model  $model  The inventoryable model (e.g., Product)
     * @param  string  $locationId  UUID of the receiving location
     * @param  int  $quantity  Positive quantity to receive
     * @param  string|null  $reason  Reference number (e.g., PO number)
     * @param  string|null  $note  Additional notes
     * @param  string|null  $userId  ID of user performing the action
     * @param  DateTimeInterface|null  $occurredAt  When the receipt occurred (defaults to now)
     * @return InventoryMovement The created movement record
     *
     * @throws InvalidArgumentException If quantity is not positive
     */
    public function receive(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        return app(ReceiveInventory::class)->handle($model, $locationId, $quantity, $reason, $note, $userId, $occurredAt);
    }

    /**
     * Ship inventory from a location.
     */
    public function ship(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        return app(ShipInventory::class)->handle($model, $locationId, $quantity, $reason, $reference, $note, $userId, $occurredAt);
    }

    /**
     * Transfer inventory between locations.
     */
    public function transfer(
        Model $model,
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        return app(TransferInventory::class)->handle($model, $fromLocationId, $toLocationId, $quantity, $note, $userId, $occurredAt);
    }

    /**
     * Adjust inventory to a specific quantity at a location.
     */
    public function adjust(
        Model $model,
        string $locationId,
        int $newQuantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        return app(AdjustInventory::class)->handle($model, $locationId, $newQuantity, $reason, $note, $userId, $occurredAt);
    }

    /** @return array<string, int> */
    public function getAvailability(Model $model): array
    {
        return app(InventoryStockQuery::class)->getAvailability($model);
    }

    public function getTotalAvailable(Model $model): int
    {
        return app(InventoryStockQuery::class)->getTotalAvailable($model);
    }

    /**
     * @param  iterable<int, Model>  $models
     * @return array<string, int>
     */
    public function getAvailabilityForMany(iterable $models): array
    {
        return app(InventoryStockQuery::class)->getAvailabilityForMany($models);
    }

    public function getTotalOnHand(Model $model): int
    {
        return app(InventoryStockQuery::class)->getTotalOnHand($model);
    }

    public function hasInventory(Model $model, int $quantity): bool
    {
        return app(InventoryStockQuery::class)->hasInventory($model, $quantity);
    }

    public function getLevel(Model $model, string $locationId): ?InventoryLevel
    {
        return app(InventoryStockQuery::class)->getLevel($model, $locationId);
    }

    /**
     * Get or create inventory level at a location.
     */
    public function getOrCreateLevel(Model $model, string $locationId): InventoryLevel
    {
        return app(InventoryLevelStore::class)->getOrCreate($model, $locationId);
    }

    /** @return Collection<int, InventoryMovement> */
    public function getMovementHistory(Model $model, int $limit = 50): Collection
    {
        return app(InventoryStockQuery::class)->getMovementHistory($model, $limit);
    }

    /**
     * Initialize inventory only when the item has no stock levels in the
     * current writable owner scope. Never replenish an existing level.
     */
    public function bootstrapAtDefault(
        Model $model,
        int $minimumOnHand,
        ?string $reason = null,
        ?string $note = null,
    ): void {
        app(BootstrapInventory::class)->execute($model, $minimumOnHand, $reason, $note);
    }

    /**
     * Receive inventory at the default location (for simple setups).
     */
    public function receiveAtDefault(
        Model $model,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return app(ReceiveAtDefault::class)->handle($model, $quantity, $reason, $note, $userId);
    }

    /**
     * Ship inventory from the default location (for simple setups).
     */
    public function shipFromDefault(
        Model $model,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return app(ShipFromDefault::class)->handle($model, $quantity, $reason, $reference, $note, $userId);
    }
}
