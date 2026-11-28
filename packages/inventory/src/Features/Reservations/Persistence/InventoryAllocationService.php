<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Persistence;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Contracts\BatchReservationBalances;
use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Events\InventoryAllocated;
use AIArmada\Inventory\Events\InventoryReleased;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Features\Reservations\Models\InventoryAllocation;
use AIArmada\Inventory\Features\Reservations\Models\InventoryReservation;
use AIArmada\Inventory\Features\Stock\Api\AllocationCandidates;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Stock\Persistence\ReservationStockStore;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Manages inventory allocations (reservations) for shopping carts.
 *
 * Allocations temporarily reserve stock while customers shop, preventing
 * overselling. Allocations have a TTL and are automatically released if
 * not committed (converted to actual shipments) within that time.
 *
 * @example Allocate stock for a cart
 * ```php
 * $service = app(InventoryAllocationService::class);
 * $allocations = $service->allocate($product, 5, $cartId, ttlMinutes: 30);
 * ```
 * @example Release allocations when cart is abandoned
 * ```php
 * $service->releaseAllForCart($cartId);
 * ```
 * @example Commit allocations after payment
 * ```php
 * $movements = $service->commitForCart($cartId, $orderId);
 * ```
 * @example Check available stock (considers allocations)
 * ```php
 * $available = $service->getTotalAvailable($product);
 * ```
 */
final class InventoryAllocationService
{
    public function __construct(
        private InventoryService $inventoryService,
        private ReservationStockStore $stock,
        private AllocationCandidates $candidates,
    ) {}

    /**
     * Allocate inventory for a cart.
     *
     * Reserves stock across one or more locations based on the configured
     * allocation strategy. If insufficient stock is available, throws an
     * exception unless backorders are enabled.
     *
     * @param  Model  $model  The inventoryable model (e.g., Product)
     * @param  int  $quantity  Quantity to allocate
     * @param  string  $cartId  Cart identifier for this allocation
     * @param  int  $ttlMinutes  How long the allocation remains valid
     * @return Collection<int, InventoryAllocation> Created allocation records
     *
     * @throws InvalidArgumentException If quantity or TTL is not positive
     * @throws InsufficientInventoryException If insufficient stock available
     */
    public function allocate(
        Model $model,
        int $quantity,
        string $cartId,
        int $ttlMinutes = 30,
        ?string $reservationGroupId = null,
    ): Collection {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        if ($ttlMinutes <= 0) {
            throw new InvalidArgumentException('TTL minutes must be positive');
        }

        if (mb_trim($cartId) === '') {
            throw new InvalidArgumentException('Allocation reference must not be empty');
        }

        return DB::transaction(function () use ($model, $quantity, $cartId, $ttlMinutes, $reservationGroupId): Collection {
            if ($reservationGroupId === null) {
                $this->release($model, $cartId);
            } else {
                $groupQuery = InventoryReservation::query()
                    ->whereKey($reservationGroupId)
                    ->where('reference', $cartId)
                    ->where('status', InventoryReservation::STATE_RESERVED);

                if (InventoryOwnerScope::isEnabled()) {
                    $owner = InventoryOwnerScope::resolveOwner();
                    OwnerContext::assertResolvedOrExplicitGlobal($owner);
                    $groupQuery->forOwner($owner, false);
                }

                $groupQuery->lockForUpdate()->firstOrFail();
            }

            $strategy = $this->getStrategy($model);
            $allowSplit = $strategy->allowsSplit() && config('inventory.allow_split_allocation', true);

            $levels = $this->candidates->locked($model->getMorphClass(), (string) $model->getKey(), $strategy);
            /** @var Collection<int, InventoryAllocation> $allocations */
            $allocations = new Collection;
            $remaining = $quantity;
            $expiresAt = CarbonImmutable::now()->addMinutes($ttlMinutes);

            foreach ($levels as $level) {
                if ($remaining <= 0) {
                    break;
                }

                $available = $level['available'];

                if ($available <= 0) {
                    continue;
                }

                $toAllocate = $allowSplit ? min($remaining, $available) : ($available >= $remaining ? $remaining : 0);

                if ($toAllocate <= 0) {
                    continue;
                }

                // Create allocation record
                $allocation = InventoryAllocation::create([
                    'inventoryable_type' => $model->getMorphClass(),
                    'inventoryable_id' => $model->getKey(),
                    'location_id' => $level['location_id'],
                    'level_id' => $level['id'],
                    'cart_id' => $cartId,
                    'reservation_group_id' => $reservationGroupId,
                    'quantity' => $toAllocate,
                    'expires_at' => $expiresAt,
                ]);

                // Update reserved quantity
                $this->stock->reserve($level['id'], $toAllocate);

                $allocations->push($allocation);
                $remaining -= $toAllocate;

                // For single location strategy, break after first allocation
                if (! $allowSplit) {
                    break;
                }
            }

            if ($remaining > 0) {
                // The enclosing transaction rolls back every allocation and stock update.
                $allocatedQuantity = $quantity - $remaining;

                throw new InsufficientInventoryException(
                    sprintf('Insufficient inventory. Requested: %d, Available: %d', $quantity, $allocatedQuantity),
                    (string) $model->getKey(),
                    $quantity,
                    $allocatedQuantity
                );
            }

            if ($allocations->isNotEmpty()) {
                Event::dispatch(new InventoryAllocated($model, $allocations, $cartId));
            }

            return $allocations;
        });
    }

    /**
     * Release a single allocation directly.
     *
     * @param  InventoryAllocation  $allocation  The allocation to release
     * @return int Released quantity (0 if allocation not found or already released)
     */
    public function releaseAllocation(InventoryAllocation $allocation): int
    {
        return DB::transaction(function () use ($allocation): int {
            $allocationQuery = InventoryOwnerScope::forMutation(
                InventoryAllocation::query()
                    ->whereKey($allocation->getKey())
                    ->whereNull('reservation_group_id')
                    ->lockForUpdate()
                    ->with('location')
            );

            $lockedAllocation = $allocationQuery->first();

            if ($lockedAllocation === null) {
                return 0;
            }

            // Ensure relationships are loaded
            $lockedAllocation->loadMissing(['level', 'inventoryable']);

            $quantity = $lockedAllocation->quantity;
            $this->releaseBalance($lockedAllocation);

            $inventoryable = $lockedAllocation->inventoryable;
            $cartId = $lockedAllocation->cart_id;

            $lockedAllocation->delete();

            if ($inventoryable !== null) {
                Event::dispatch(new InventoryReleased($inventoryable, $quantity, $cartId));
            }

            return $quantity;
        });
    }

    /**
     * Release allocations for a model/cart.
     *
     * Releases all allocations matching the model and cart ID combination.
     * Use this when a customer removes an item from their cart.
     *
     * @param  Model  $model  The inventoryable model
     * @param  string  $cartId  Cart identifier
     * @return int Total quantity released
     */
    public function release(Model $model, string $cartId): int
    {
        return DB::transaction(function () use ($model, $cartId): int {
            $allocationsQuery = InventoryOwnerScope::forMutation(
                InventoryAllocation::query()
                    ->where('inventoryable_type', $model->getMorphClass())
                    ->where('inventoryable_id', $model->getKey())
                    ->forCart($cartId)->whereNull('reservation_group_id')
                    ->with('location')
                    ->lockForUpdate()
            );

            $allocations = $allocationsQuery->get();

            $totalReleased = 0;

            foreach ($allocations as $allocation) {
                $this->releaseBalance($allocation);
                $totalReleased += $allocation->quantity;
                $allocation->delete();
            }

            if ($totalReleased > 0) {
                Event::dispatch(new InventoryReleased($model, $totalReleased, $cartId));
            }

            return $totalReleased;
        });
    }

    /**
     * Release all allocations for a cart (across all products).
     *
     * Use this when a cart is abandoned, cleared, or cancelled.
     * Releases reserved stock back to available inventory.
     *
     * @param  string  $cartId  Cart identifier
     * @return int Total quantity released across all products
     */
    public function releaseAllForCart(string $cartId): int
    {
        return DB::transaction(function () use ($cartId): int {
            $allocationsQuery = InventoryOwnerScope::forMutation(
                InventoryAllocation::query()
                    ->forCart($cartId)->whereNull('reservation_group_id')
                    ->with('level')
                    ->with('location')
                    ->lockForUpdate()
            );

            $allocations = $allocationsQuery->get();

            $totalReleased = 0;

            foreach ($allocations as $allocation) {
                $this->releaseBalance($allocation);
                $totalReleased += $allocation->quantity;
                $allocation->delete();
            }

            return $totalReleased;
        });
    }

    /**
     * Release only the allocations owned by one checkout reservation group.
     */
    public function releaseAllForReservationGroup(string $reservationGroupId): int
    {
        return DB::transaction(function () use ($reservationGroupId): int {
            $allocationsQuery = InventoryOwnerScope::forMutation(
                InventoryAllocation::query()
                    ->where('reservation_group_id', $reservationGroupId)
                    ->with(['level', 'location'])
                    ->lockForUpdate()
            );

            $allocations = $allocationsQuery->get();
            $totalReleased = 0;

            foreach ($allocations as $allocation) {
                $this->releaseBalance($allocation);
                $totalReleased += $allocation->quantity;
                $allocation->delete();
            }

            return $totalReleased;
        });
    }

    /**
     * Commit allocations (convert to shipments after payment).
     *
     * Call this after a successful payment to convert reserved stock into
     * actual shipments. Creates inventory movements and updates stock levels.
     *
     * @param  string  $cartId  Cart identifier with allocations to commit
     * @param  string|null  $orderId  Optional order ID for movement reference
     * @return array<InventoryMovement> Created shipment movements
     */
    public function commit(string $cartId, ?string $orderId = null): array
    {
        return DB::transaction(function () use ($cartId, $orderId): array {
            $allocationsQuery = InventoryOwnerScope::forMutation(
                InventoryAllocation::query()
                    ->forCart($cartId)->whereNull('reservation_group_id')
                    ->with(['level', 'inventoryable'])
                    ->with('location')
                    ->lockForUpdate()
            );

            $allocations = $allocationsQuery->get();

            $movements = [];

            foreach ($allocations as $allocation) {
                $this->commitBatchBalance($allocation);
                $movements[] = $this->stock->commit(
                    $allocation->level,
                    $allocation->quantity,
                    $orderId,
                    sprintf('Committed from cart %s', $cartId),
                    $allocation->batch_id,
                );
                $allocation->delete();
            }

            return $movements;
        });
    }

    /**
     * Commit only the allocations owned by one checkout reservation group.
     *
     * @return array<InventoryMovement>
     */
    public function commitReservationGroup(string $reservationGroupId, string $reference, ?string $orderId = null): array
    {
        return DB::transaction(function () use ($reservationGroupId, $reference, $orderId): array {
            $allocationsQuery = InventoryOwnerScope::forMutation(
                InventoryAllocation::query()
                    ->where('reservation_group_id', $reservationGroupId)
                    ->with(['level', 'inventoryable', 'location'])
                    ->lockForUpdate()
            );

            $allocations = $allocationsQuery->get();
            $movements = [];

            foreach ($allocations as $allocation) {
                $this->commitBatchBalance($allocation);
                $movements[] = $this->stock->commit(
                    $allocation->level,
                    $allocation->quantity,
                    $orderId,
                    sprintf('Committed from checkout reference %s', $reference),
                    $allocation->batch_id,
                );
                $allocation->delete();
            }

            return $movements;
        });
    }

    /**
     * Extend allocations for a cart.
     *
     * Pushes the expiration time forward. Call this when a customer
     * shows activity (page views, cart updates) to prevent early expiration.
     *
     * @param  string  $cartId  Cart identifier
     * @param  int  $minutes  Additional minutes to extend
     * @return int Number of allocations extended
     */
    public function extendAllocations(string $cartId, int $minutes): int
    {
        if ($minutes <= 0) {
            throw new InvalidArgumentException('TTL minutes must be positive');
        }

        $newExpiry = CarbonImmutable::now()->addMinutes($minutes);

        $query = InventoryOwnerScope::forMutation(
            InventoryAllocation::query()->forCart($cartId)->whereNull('reservation_group_id')->with('location')
        );

        return $query->update(['expires_at' => $newExpiry]);
    }

    public function extendReservationGroupAllocations(string $reservationGroupId, int $minutes): int
    {
        if ($minutes <= 0) {
            throw new InvalidArgumentException('TTL minutes must be positive');
        }

        return InventoryOwnerScope::forMutation(
            InventoryAllocation::query()
                ->where('reservation_group_id', $reservationGroupId)
                ->with('location')
        )->update(['expires_at' => CarbonImmutable::now()->addMinutes($minutes)]);
    }

    /**
     * Get allocations for a cart.
     *
     * @return Collection<int, InventoryAllocation>
     */
    public function getAllocationsForCart(string $cartId): Collection
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryAllocation::query()
                ->forCart($cartId)->whereNull('reservation_group_id')
                ->active()
                ->with(['location', 'level', 'inventoryable'])
        );

        return $query->get();
    }

    /**
     * Get allocation for a specific model/cart.
     *
     * @return Collection<int, InventoryAllocation>
     */
    public function getAllocations(Model $model, string $cartId): Collection
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryAllocation::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->forCart($cartId)->whereNull('reservation_group_id')
                ->active()
                ->with('location')
        );

        return $query->get();
    }

    /**
     * Check if a model has sufficient available inventory.
     */
    public function hasAvailableInventory(Model $model, int $quantity): bool
    {
        return $this->inventoryService->getTotalAvailable($model) >= $quantity;
    }

    /**
     * Get total available for a model (convenience method).
     */
    public function getTotalAvailable(Model $model): int
    {
        return $this->inventoryService->getTotalAvailable($model);
    }

    /**
     * Validate availability for multiple items.
     *
     * Checks if all items can be fulfilled. Use this before attempting
     * allocation to provide user-friendly messaging about unavailable items.
     *
     * @param  array<array{model: Model, quantity: int}>  $items  Items to validate
     * @return array{available: bool, issues: array<array{model: Model, requested: int, available: int}>}
     *
     * @example
     * ```php
     * $result = $service->validateAvailability([
     *     ['model' => $productA, 'quantity' => 2],
     *     ['model' => $productB, 'quantity' => 5],
     * ]);
     *
     * if (!$result['available']) {
     *     foreach ($result['issues'] as $issue) {
     *         echo "{$issue['model']->name}: only {$issue['available']} available";
     *     }
     * }
     * ```
     */
    public function validateAvailability(array $items): array
    {
        $issues = [];
        $demand = [];

        foreach ($items as $item) {
            $model = $item['model'];
            $requested = $item['quantity'];

            if ($requested <= 0) {
                throw new InvalidArgumentException('Quantity must be positive');
            }

            $key = $model->getMorphClass() . ':' . $model->getKey();

            if (! isset($demand[$key])) {
                $demand[$key] = ['model' => $model, 'quantity' => 0];
            }

            if ($requested > PHP_INT_MAX - $demand[$key]['quantity']) {
                throw new InvalidArgumentException('Aggregate inventory demand exceeds the supported quantity');
            }

            $demand[$key]['quantity'] += $requested;
        }

        $totals = $this->inventoryService->getAvailabilityForMany(array_column($demand, 'model'));

        foreach ($demand as $key => $item) {
            $available = $totals[$key] ?? 0;

            if ($available < $item['quantity']) {
                $issues[] = [
                    'model' => $item['model'],
                    'requested' => $item['quantity'],
                    'available' => $available,
                ];
            }
        }

        return [
            'available' => $issues === [],
            'issues' => $issues,
        ];
    }

    /**
     * Clean up expired allocations.
     *
     * Releases all allocations past their expiration time. Call this
     * periodically via a scheduled command to free up reserved stock.
     *
     * @return int Number of allocations cleaned up
     */
    public function cleanupExpired(): int
    {
        return DB::transaction(function (): int {
            $allocationsQuery = InventoryOwnerScope::forMutation(
                InventoryAllocation::query()
                    ->expired()
                    ->whereNull('reservation_group_id')
                    ->with(['level', 'location'])
                    ->lockForUpdate()
            );

            $allocations = $allocationsQuery->get();

            return $this->cleanupAllocationCollection($allocations);
        });
    }

    /**
     * Clean up expired allocations across all owners (maintenance).
     *
     * Use this in system-level scheduled tasks that run without
     * owner context. Bypasses owner scoping for maintenance purposes.
     *
     * @return int Number of allocations cleaned up
     */
    public function cleanupExpiredGlobal(): int
    {
        return (int) (new OwnerBatchRunner(InventoryAllocation::class))->run(fn (): int => $this->cleanupExpired());
    }

    /**
     * @param  Collection<int, InventoryAllocation>  $allocations
     */
    private function cleanupAllocationCollection(Collection $allocations): int
    {
        $count = 0;

        foreach ($allocations as $allocation) {
            $this->releaseBalance($allocation);
            $allocation->delete();
            $count++;
        }

        return $count;
    }

    private function releaseBalance(InventoryAllocation $allocation): void
    {
        if ($allocation->batch_id !== null) {
            app(BatchReservationBalances::class)->release(
                $allocation->batch_id,
                $allocation->inventoryable_type,
                $allocation->inventoryable_id,
                $allocation->location_id,
                $allocation->quantity,
            );
        }

        $this->stock->release($allocation->level, $allocation->quantity);
    }

    private function commitBatchBalance(InventoryAllocation $allocation): void
    {
        if ($allocation->batch_id !== null) {
            app(BatchReservationBalances::class)->commit(
                $allocation->batch_id,
                $allocation->inventoryable_type,
                $allocation->inventoryable_id,
                $allocation->location_id,
                $allocation->quantity,
            );
        }
    }

    /**
     * Get the effective allocation strategy for a model.
     */
    public function getStrategy(Model $model): AllocationStrategy
    {
        // Check if model implements InventoryableInterface and has custom strategy
        if (method_exists($model, 'getAllocationStrategy')) {
            $strategy = $model->getAllocationStrategy();

            if ($strategy !== null) {
                return $strategy;
            }
        }

        // Fall back to global config
        $global = config('inventory.allocation_strategy', 'priority');

        return AllocationStrategy::from($global);
    }
}
