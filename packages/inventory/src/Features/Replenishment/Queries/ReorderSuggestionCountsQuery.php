<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Replenishment\Queries;

use AIArmada\Inventory\Enums\ReorderSuggestionStatus;
use AIArmada\Inventory\Enums\ReorderUrgency;
use AIArmada\Inventory\Features\Replenishment\Api\ReorderSuggestionCounts;
use AIArmada\Inventory\Features\Replenishment\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Support\InventoryOwnerScope;

final readonly class ReorderSuggestionCountsQuery implements ReorderSuggestionCounts
{
    public function counts(string $currency): array
    {
        $query = InventoryOwnerScope::applyToLocationQuery(InventoryReorderSuggestion::query());

        if (InventoryOwnerScope::isEnabled() && ! InventoryOwnerScope::includeGlobal() && ! InventoryOwnerScope::isCurrentContextGlobalOnly()) {
            $query->whereNotNull('location_id');
        }

        $counts = (clone $query)->toBase()
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS pending', [ReorderSuggestionStatus::Pending->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS approved', [ReorderSuggestionStatus::Approved->value])
            ->selectRaw('COUNT(CASE WHEN status = ? AND urgency = ? THEN 1 END) AS urgent', [ReorderSuggestionStatus::Pending->value, ReorderUrgency::Critical->value])
            ->first();

        $suggestedValue = 0;
        $unpriced = 0;

        foreach ($query->actionable()->with('supplierLeadtime')->lazyById(200) as $suggestion) {
            $supplier = $suggestion->supplierLeadtime;

            if ($supplier === null
                || $supplier->currency !== $currency
                || $supplier->inventoryable_type !== $suggestion->inventoryable_type
                || $supplier->inventoryable_id !== $suggestion->inventoryable_id
                || (InventoryOwnerScope::isEnabled() && ($supplier->owner_type !== $suggestion->owner_type || $supplier->owner_id !== $suggestion->owner_id))) {
                $unpriced++;

                continue;
            }

            $cost = $supplier->calculateOrderCost($suggestion->suggested_quantity);

            if ($cost === null) {
                $unpriced++;

                continue;
            }

            $suggestedValue += $cost;
        }

        return [
            'pending' => (int) ($counts->pending ?? 0),
            'approved' => (int) ($counts->approved ?? 0),
            'urgent' => (int) ($counts->urgent ?? 0),
            'suggested_value' => $suggestedValue,
            'unpriced' => $unpriced,
        ];
    }
}
