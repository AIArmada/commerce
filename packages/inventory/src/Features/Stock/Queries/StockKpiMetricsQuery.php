<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Stock\Api\LocationVisibility;
use AIArmada\Inventory\Features\Stock\Api\StockKpiMetrics;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class StockKpiMetricsQuery implements StockKpiMetrics
{
    public function __construct(private LocationVisibility $locations) {}

    public function shipments(CarbonImmutable $start, CarbonImmutable $end, ?string $type = null, ?string $id = null): array
    {
        $query = $this->movements($start, $end)
            ->where('type', MovementType::Shipment->value);

        if ($type !== null && $id !== null) {
            $query->where('inventoryable_type', $type)->where('inventoryable_id', $id);
        }

        $counts = $query->toBase()
            ->selectRaw('COALESCE(SUM(quantity), 0) AS quantity')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(CASE WHEN reason IS NULL OR reason <> 'partial' THEN 1 END) AS fulfilled")
            ->first();

        return [
            'quantity' => (int) ($counts->quantity ?? 0),
            'total' => (int) ($counts->total ?? 0),
            'fulfilled' => (int) ($counts->fulfilled ?? 0),
        ];
    }

    public function onHand(?string $type = null, ?string $id = null): int
    {
        $query = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query());

        if ($type !== null && $id !== null) {
            $query->where('inventoryable_type', $type)->where('inventoryable_id', $id);
        }

        return (int) $query->sum('quantity_on_hand');
    }

    public function cycleCounts(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $counts = $this->movements($start, $end)
            ->where('type', MovementType::Adjustment->value)
            ->where('reason', 'cycle_count')
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(CASE WHEN quantity = 0 THEN 1 END) AS accurate')
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'accurate' => (int) ($counts->accurate ?? 0),
        ];
    }

    public function skuCounts(int $threshold): array
    {
        $levels = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->select('inventoryable_type', 'inventoryable_id')
            ->selectRaw('SUM(CASE WHEN quantity_on_hand > quantity_reserved THEN quantity_on_hand - quantity_reserved ELSE 0 END) AS available')
            ->whereHas('location', fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query)->where('is_active', true))
            ->groupBy('inventoryable_type', 'inventoryable_id');

        $counts = DB::query()->fromSub($levels->toBase(), 'grouped_skus')
            ->selectRaw('COUNT(*) AS total_skus')
            ->selectRaw('COUNT(CASE WHEN available > 0 AND available <= ? THEN 1 END) AS low_stock', [$threshold])
            ->selectRaw('COUNT(CASE WHEN available = 0 THEN 1 END) AS out_of_stock')
            ->first();

        return [
            'total_skus' => (int) ($counts->total_skus ?? 0),
            'low_stock' => (int) ($counts->low_stock ?? 0),
            'out_of_stock' => (int) ($counts->out_of_stock ?? 0),
        ];
    }

    public function depletionDays(string $type, string $id, ?string $locationId, CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($locationId !== null && ! $this->locations->isVisible($locationId)) {
            throw new InvalidArgumentException('Invalid location for current owner');
        }

        $query = $this->movements($start, $end)
            ->where('inventoryable_type', $type)
            ->where('inventoryable_id', $id)
            ->where('type', MovementType::Shipment->value)
            ->where('quantity_after', 0)
            ->where('quantity_before', '>', 0);

        if ($locationId !== null) {
            $query->where('from_location_id', $locationId);
        }

        return (int) ($query->toBase()->selectRaw('COUNT(DISTINCT DATE(occurred_at)) AS days')->first()->days ?? 0);
    }

    /** @return Builder<InventoryMovement> */
    private function movements(CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        $query = InventoryMovement::query()->whereBetween('occurred_at', [$start, $end]);

        return InventoryOwnerScope::applyToMovementQuery($query);
    }
}
