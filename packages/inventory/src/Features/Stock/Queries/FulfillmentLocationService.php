<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\StockAvailability;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Queries\InventoryDemandResolver;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Provides fulfillment location suggestions for orders.
 *
 * This service helps shipping packages determine the optimal warehouse
 * or fulfillment location to ship from based on inventory availability.
 *
 * @example Get best location to fulfill an order
 * ```php
 * $fulfillment = app(FulfillmentLocationService::class);
 * $location = $fulfillment->getBestFulfillmentLocation($order);
 *
 * if ($location) {
 *     // Use $location->id as origin_location_id for shipping
 * }
 * ```
 * @example Check if order can be fulfilled from single location
 * ```php
 * $canFulfill = $fulfillment->canFulfillFromSingleLocation($order);
 * if (!$canFulfill) {
 *     // May need to split shipment across locations
 * }
 * ```
 */
final readonly class FulfillmentLocationService
{
    public function __construct(private StockAvailability $stock) {}

    /**
     * Get the best fulfillment location for an order.
     *
     * Returns the location that can fulfill the most items with priority
     * given to locations that can fulfill the entire order.
     */
    public function getBestFulfillmentLocation(Order $order): ?InventoryLocation
    {
        $itemRequirements = $this->getItemRequirements($order);

        if ($itemRequirements->isEmpty()) {
            return null;
        }

        $locations = $this->getActiveLocations();
        $bestLocation = null;
        $bestScore = null;
        $availability = $this->getAvailabilityByLocation($itemRequirements);

        foreach ($locations as $location) {
            $score = $this->calculateFulfillmentScore($location, $itemRequirements, $availability);

            if ($score[1] > 0 && ($bestScore === null || $score > $bestScore)) {
                $bestScore = $score;
                $bestLocation = $location;
            }
        }

        return $bestLocation;
    }

    /**
     * Check if the entire order can be fulfilled from a single location.
     */
    public function canFulfillFromSingleLocation(Order $order): bool
    {
        $itemRequirements = $this->getItemRequirements($order);

        if ($itemRequirements->isEmpty()) {
            return true;
        }

        $locations = $this->getActiveLocations();

        $availability = $this->getAvailabilityByLocation($itemRequirements);

        foreach ($locations as $location) {
            if ($this->locationCanFulfillAll($location, $itemRequirements, $availability)) {
                return true;
            }
        }

        return false;
    }

    public function canFulfillAtLocation(Order $order, string $locationId): bool
    {
        $requirements = $this->getItemRequirements($order);

        if ($requirements->isEmpty()) {
            return false;
        }

        $location = InventoryOwnerScope::forMutation(InventoryLocation::query())
            ->whereKey($locationId)
            ->where('is_active', true)
            ->first();

        return $location !== null
            && $this->locationCanFulfillAll($location, $requirements, $this->getAvailabilityByLocation($requirements));
    }

    /**
     * Get fulfillment plan showing which items ship from which locations.
     *
     * @return Collection<int, array{location: InventoryLocation, items: array<array{model: Model, quantity: int}>}>
     */
    public function getFulfillmentPlan(Order $order): Collection
    {
        $itemRequirements = $this->getItemRequirements($order);
        $locations = $this->getActiveLocations();
        $plan = new Collection;
        $availability = $this->getAvailabilityByLocation($itemRequirements);

        foreach ($locations as $location) {
            if ($this->locationCanFulfillAll($location, $itemRequirements, $availability)) {
                $locations = new Collection([$location]);

                break;
            }
        }

        $remaining = $itemRequirements->mapWithKeys(fn ($item) => [
            $item['key'] => $item['quantity'],
        ]);

        foreach ($locations as $location) {
            $locationItems = [];

            foreach ($itemRequirements as $item) {
                $key = $item['key'];
                $needed = $remaining->get($key, 0);

                if ($needed <= 0) {
                    continue;
                }

                $available = ($availability[$location->id][$item['key']] ?? 0);
                $fulfill = min($needed, $available);

                if ($fulfill > 0) {
                    $locationItems[] = [
                        'model' => $item['model'],
                        'quantity' => $fulfill,
                    ];
                    $remaining->put($key, $needed - $fulfill);
                }
            }

            if (count($locationItems) > 0) {
                $plan->push([
                    'location' => $location,
                    'items' => $locationItems,
                ]);
            }
        }

        return $plan;
    }

    /**
     * Get availability summary for an order across all locations.
     *
     * @return array{
     *     fully_available: bool,
     *     total_available: int,
     *     total_required: int,
     *     by_item: array<string, array{required: int, available: int, shortage: int}>
     * }
     */
    public function getAvailabilitySummary(Order $order): array
    {
        $itemRequirements = $this->getItemRequirements($order);
        $totalRequired = 0;
        $totalAvailable = 0;
        $fullyAvailable = true;
        $byItem = [];
        $totals = [];

        foreach ($this->getAvailabilityByLocation($itemRequirements) as $locationAvailability) {
            foreach ($locationAvailability as $key => $quantity) {
                $totals[$key] = ($totals[$key] ?? 0) + $quantity;
            }
        }

        foreach ($itemRequirements as $item) {
            $required = $item['quantity'];
            $available = ($totals[$item['key']] ?? 0);
            $shortage = max(0, $required - $available);

            $totalRequired += $required;
            $totalAvailable += min($required, $available);

            if ($shortage > 0) {
                $fullyAvailable = false;
            }

            $byItem[$item['key']] = [
                'required' => $required,
                'available' => $available,
                'shortage' => $shortage,
            ];
        }

        return [
            'fully_available' => $fullyAvailable,
            'total_available' => $totalAvailable,
            'total_required' => $totalRequired,
            'by_item' => $byItem,
        ];
    }

    /**
     * Extract item requirements from order.
     *
     * @return Collection<int, array{model: Model, quantity: int, key: string}>
     */
    private function getItemRequirements(Order $order): Collection
    {
        $order->loadMissing('items.purchasable');

        return collect(InventoryDemandResolver::forOrder($order->items))
            ->map(static fn (array $entry, string $key): array => [
                'model' => $entry['model'],
                'quantity' => $entry['quantity'],
                'key' => $key,
            ])->values();
    }

    /**
     * Get active inventory locations.
     *
     * @return Collection<int, InventoryLocation>
     */
    private function getActiveLocations(): Collection
    {
        return InventoryOwnerScope::forMutation(
            InventoryLocation::query()
                ->where('is_active', true)
                ->orderByDesc('priority')
                ->orderBy('id')
        )->get();
    }

    /**
     * Calculate a score for how well a location can fulfill requirements.
     *
     * @param  Collection<int, array{model: Model, quantity: int, key: string}>  $requirements
     * @param  array<string, array<string, int>>  $availability
     * @return array{int, int, int}
     */
    private function calculateFulfillmentScore(InventoryLocation $location, Collection $requirements, array $availability): array
    {
        $fulfilled = 0;
        $canFulfillAll = true;

        foreach ($requirements as $item) {
            $available = $availability[$location->id][$item['key']] ?? 0;
            $needed = $item['quantity'];
            $fulfilled += min($needed, $available);
            $canFulfillAll = $canFulfillAll && $available >= $needed;
        }

        return [(int) $canFulfillAll, $fulfilled, $location->priority ?? 0];
    }

    /**
     * Check if a location can fulfill all requirements.
     *
     * @param  Collection<int, array{model: Model, quantity: int, key: string}>  $requirements
     * @param  array<string, array<string, int>>  $availability
     */
    private function locationCanFulfillAll(InventoryLocation $location, Collection $requirements, array $availability): bool
    {
        foreach ($requirements as $item) {
            $available = ($availability[$location->id][$item['key']] ?? 0);

            if ($available < $item['quantity']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, array{model: Model, quantity: int, key: string}>  $requirements
     * @return array<string, array<string, int>>
     */
    private function getAvailabilityByLocation(Collection $requirements): array
    {
        if ($requirements->isEmpty()) {
            return [];
        }

        $items = $requirements->map(static fn (array $item): array => [
            'type' => $item['model']->getMorphClass(),
            'id' => (string) $item['model']->getKey(),
        ])->all();

        $availability = [];

        foreach ($this->stock->forAllocation($items) as $row) {
            $key = $row['type'] . ':' . $row['id'];
            $availability[$row['location_id']][$key] = $row['available'];
        }

        return $availability;
    }
}
