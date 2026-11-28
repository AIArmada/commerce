<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Persistence;

use AIArmada\Inventory\Features\Valuation\Models\InventoryStandardCost;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class StandardCostService
{
    /**
     * Set a new standard cost for a model.
     */
    public function setStandardCost(
        Model $model,
        int $standardCostMinor,
        CarbonImmutable $effectiveFrom,
        ?CarbonImmutable $effectiveTo = null,
        ?string $approvedBy = null,
        ?string $notes = null,
        ?array $metadata = null
    ): InventoryStandardCost {
        if ($standardCostMinor < 0 || ($effectiveTo !== null && $effectiveTo <= $effectiveFrom)) {
            throw new InvalidArgumentException('Standard cost must be non-negative and its end must follow its start.');
        }

        return DB::transaction(function () use (
            $model,
            $standardCostMinor,
            $effectiveFrom,
            $effectiveTo,
            $approvedBy,
            $notes,
            $metadata
        ): InventoryStandardCost {
            $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $currentCost = $this->mutationQuery($model)->effectiveAt($effectiveFrom)->orderByDesc('effective_from')->lockForUpdate()->first();
            if ($currentCost !== null && $currentCost->effective_from < $effectiveFrom) {
                $currentCost->update(['effective_to' => $effectiveFrom]);
            } elseif ($currentCost !== null) {
                throw new InvalidArgumentException('A standard cost already starts at this date.');
            }

            $next = $this->mutationQuery($model)->where('effective_from', '>', $effectiveFrom)->orderBy('effective_from')->first();
            if ($next !== null && ($effectiveTo === null || $effectiveTo > $next->effective_from)) {
                $effectiveTo = $next->effective_from;
            }

            $cost = new InventoryStandardCost([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'standard_cost_minor' => $standardCostMinor,
                'currency' => config('inventory.defaults.currency', 'MYR'),
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'approved_by' => $approvedBy,
                'notes' => $notes,
                'metadata' => $metadata,
            ]);

            if (InventoryOwnerScope::isEnabled() && ($owner = InventoryOwnerScope::resolveOwner()) !== null) {
                $cost->assignOwner($owner);
            }

            $cost->save();

            return $cost;
        }, 3);
    }

    /**
     * Get current standard cost for a model.
     */
    public function getCurrentStandardCost(Model $model): ?InventoryStandardCost
    {
        return $this->readQuery($model)
            ->current()
            ->orderByDesc('effective_from')
            ->orderBy('id')
            ->first();
    }

    /**
     * Get standard cost effective at a specific date.
     */
    public function getStandardCostAt(Model $model, CarbonImmutable $date): ?InventoryStandardCost
    {
        return $this->readQuery($model)
            ->effectiveAt($date)
            ->orderByDesc('effective_from')
            ->orderBy('id')
            ->first();
    }

    /**
     * Get current standard cost value in minor units.
     */
    public function getCurrentCostValue(Model $model): ?int
    {
        $cost = $this->getCurrentStandardCost($model);

        return $cost?->standard_cost_minor;
    }

    /**
     * Get cost value at a specific date.
     */
    public function getCostValueAt(Model $model, CarbonImmutable $date): ?int
    {
        $cost = $this->getStandardCostAt($model, $date);

        return $cost?->standard_cost_minor;
    }

    /**
     * Calculate valuation using standard cost.
     *
     * @return array{quantity: int, value: int, unit_cost: int|null}
     */
    public function calculateValuation(Model $model, int $quantity): array
    {
        $standardCost = $this->getCurrentCostValue($model);

        return [
            'quantity' => $quantity,
            'value' => $standardCost !== null ? $quantity * $standardCost : 0,
            'unit_cost' => $standardCost,
        ];
    }

    /**
     * Calculate variance between actual and standard cost.
     *
     * @return array{variance: int, variance_percentage: float|null, favorable: bool|null}
     */
    public function calculateVariance(Model $model, int $actualCostMinor): array
    {
        $standardCost = $this->getCurrentCostValue($model);

        if ($standardCost === null) {
            return [
                'variance' => 0,
                'variance_percentage' => null,
                'favorable' => null,
            ];
        }

        $variance = $actualCostMinor - $standardCost;
        $variancePercentage = $standardCost > 0
            ? ($variance / $standardCost) * 100
            : null;

        return [
            'variance' => $variance,
            'variance_percentage' => $variancePercentage,
            'favorable' => $variance < 0,
        ];
    }

    /**
     * Get standard cost history for a model.
     *
     * @return Collection<int, InventoryStandardCost>
     */
    public function getCostHistory(Model $model): Collection
    {
        return $this->readQuery($model)
            ->orderBy('effective_from', 'desc')
            ->get();
    }

    /**
     * Get upcoming standard cost changes.
     *
     * @return Collection<int, InventoryStandardCost>
     */
    public function getFutureCosts(Model $model): Collection
    {
        return $this->readQuery($model)
            ->future()
            ->orderBy('effective_from', 'asc')
            ->get();
    }

    /**
     * Expire the current standard cost.
     */
    public function expireCurrentCost(Model $model): bool
    {
        return DB::transaction(function () use ($model): bool {
            $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $currentCost = $this->mutationQuery($model)->current()->orderByDesc('effective_from')->lockForUpdate()->first();

            return $currentCost?->expire() ?? false;
        }, 3);
    }

    /**
     * Check if model has a current standard cost.
     */
    public function hasStandardCost(Model $model): bool
    {
        return $this->readQuery($model)
            ->current()
            ->exists();
    }

    /**
     * Schedule a future standard cost change.
     */
    public function scheduleCostChange(
        Model $model,
        int $newCostMinor,
        CarbonImmutable $effectiveFrom,
        ?string $approvedBy = null,
        ?string $notes = null
    ): InventoryStandardCost {
        if ($effectiveFrom <= CarbonImmutable::now()) {
            throw new InvalidArgumentException('Scheduled cost changes must be in the future');
        }

        return $this->setStandardCost($model, $newCostMinor, $effectiveFrom, approvedBy: $approvedBy, notes: $notes);
    }

    /**
     * Cancel a scheduled future cost change.
     */
    public function cancelScheduledCost(InventoryStandardCost $scheduledCost): bool
    {
        return DB::transaction(function () use ($scheduledCost): bool {
            $locked = InventoryOwnerScope::forMutation(InventoryStandardCost::query())
                ->whereKey($scheduledCost->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isFuture()) {
                throw new InvalidArgumentException('Can only cancel future scheduled costs');
            }

            $predecessor = InventoryOwnerScope::forMutation(InventoryStandardCost::query())
                ->where('inventoryable_type', $locked->inventoryable_type)
                ->where('inventoryable_id', $locked->inventoryable_id)
                ->where('currency', $locked->currency)
                ->where('effective_to', $locked->effective_from)
                ->where('effective_from', '<', $locked->effective_from)
                ->orderByDesc('effective_from')
                ->lockForUpdate()->first();

            if ($predecessor !== null) {
                $predecessor->update(['effective_to' => $locked->effective_to]);
            }

            return (bool) $locked->delete();
        }, 3);
    }

    /** @return Builder<InventoryStandardCost> */
    private function mutationQuery(Model $model): Builder
    {
        return InventoryOwnerScope::forMutation(InventoryStandardCost::query())->forModel($model)
            ->where('currency', (string) config('inventory.defaults.currency', 'MYR'));
    }

    /** @return Builder<InventoryStandardCost> */
    private function readQuery(Model $model): Builder
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryStandardCost::query())->forModel($model)
            ->where('currency', (string) config('inventory.defaults.currency', 'MYR'));
    }
}
