<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Queries;

use AIArmada\Inventory\Features\Stock\Api\StockBalances;
use AIArmada\Inventory\Features\Valuation\Models\InventoryStandardCost;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class StandardStockValuesQuery
{
    public function __construct(private StockBalances $balances) {}

    /**
     * @param  list<array{inventoryable_type: string, inventoryable_id: string, location_id: string}>|null  $items
     * @return iterable<array{inventoryable_type: string, inventoryable_id: string, location_id: string, quantity: int, total_value: int}>
     */
    public function rows(string $currency, ?array $items = null, ?string $locationId = null, bool $exactOwner = false): iterable
    {
        $now = CarbonImmutable::now();
        $chunk = [];

        foreach ($this->balances->rows($items, $locationId, $exactOwner) as $balance) {
            $chunk[] = $balance;

            if (count($chunk) === 200) {
                yield from $this->valueChunk($chunk, $currency, $now);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            yield from $this->valueChunk($chunk, $currency, $now);
        }
    }

    /**
     * @param  list<array{inventoryable_type: string, inventoryable_id: string, location_id: string, owner_type: string|null, owner_id: string|null, quantity: int}>  $balances
     * @return iterable<array{inventoryable_type: string, inventoryable_id: string, location_id: string, quantity: int, total_value: int}>
     */
    private function valueChunk(array $balances, string $currency, CarbonImmutable $now): iterable
    {
        $costs = InventoryOwnerScope::applyToLocationQuery(InventoryStandardCost::query())
            ->effectiveAt($now)
            ->where('currency', $currency)
            ->where(function (Builder $query) use ($balances): void {
                foreach ($balances as $balance) {
                    $query->orWhere(function (Builder $match) use ($balance): void {
                        $match->where('inventoryable_type', $balance['inventoryable_type'])
                            ->where('inventoryable_id', $balance['inventoryable_id']);
                    });
                }
            })
            ->orderByDesc('effective_from')
            ->orderBy('id')
            ->get();

        $unitCosts = [];

        foreach ($costs as $cost) {
            $key = $this->key($cost->inventoryable_type, $cost->inventoryable_id, $cost->owner_type, $cost->owner_id === null ? null : (string) $cost->owner_id);
            $unitCosts[$key] ??= $cost->standard_cost_minor;
        }

        foreach ($balances as $balance) {
            $key = $this->key($balance['inventoryable_type'], $balance['inventoryable_id'], $balance['owner_type'], $balance['owner_id']);

            if (! array_key_exists($key, $unitCosts)) {
                continue;
            }

            yield [
                'inventoryable_type' => $balance['inventoryable_type'],
                'inventoryable_id' => $balance['inventoryable_id'],
                'location_id' => $balance['location_id'],
                'quantity' => $balance['quantity'],
                'total_value' => $balance['quantity'] * $unitCosts[$key],
            ];
        }
    }

    private function key(string $type, string $id, ?string $ownerType, ?string $ownerId): string
    {
        return json_encode([$type, $id, $ownerType, $ownerId], JSON_THROW_ON_ERROR);
    }
}
