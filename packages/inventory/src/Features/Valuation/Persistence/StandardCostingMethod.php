<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Persistence;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Features\Stock\Api\LocationVisibility;
use AIArmada\Inventory\Features\Valuation\Queries\StandardStockValuesQuery;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

final readonly class StandardCostingMethod
{
    public function __construct(
        private StandardCostService $service,
        private StandardStockValuesQuery $values,
        private LocationVisibility $locations,
    ) {}

    public function supports(): CostingMethod
    {
        return CostingMethod::Standard;
    }

    /** @return array{quantity: int, value: int, average_cost: int} */
    public function calculateValuation(Model $model, ?string $locationId = null): array
    {
        if ($locationId !== null && ! $this->locations->isVisible($locationId)) {
            throw new InvalidArgumentException('Invalid location for current owner');
        }

        $quantity = 0;
        $value = 0;

        foreach ($this->values->rows(
            (string) config('inventory.defaults.currency', 'MYR'),
            locationId: $locationId,
            inventoryableType: $model->getMorphClass(),
            inventoryableId: (string) $model->getKey(),
            includeUnpriced: true,
        ) as $row) {
            $quantity += $row['quantity'];
            $value += $row['total_value'];
        }

        return [
            'quantity' => $quantity,
            'value' => $value,
            'average_cost' => $quantity > 0 ? (int) round($value / $quantity) : 0,
        ];
    }

    public function consume(Model $model, int $quantity, ?string $locationId = null): array
    {
        throw new RuntimeException('Standard cost does not support layer-level consume.');
    }

    public function estimateCogs(Model $model, int $quantity, ?string $locationId = null): int
    {
        $unitCost = $this->service->getCurrentCostValue($model);

        return $unitCost !== null ? $unitCost * $quantity : 0;
    }
}
