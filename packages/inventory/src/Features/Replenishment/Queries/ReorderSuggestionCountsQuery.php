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
    public function counts(): array
    {
        $query = InventoryOwnerScope::applyToLocationQuery(InventoryReorderSuggestion::query());

        if (InventoryOwnerScope::isEnabled() && ! InventoryOwnerScope::includeGlobal() && ! InventoryOwnerScope::isCurrentContextGlobalOnly()) {
            $query->whereNotNull('location_id');
        }

        $counts = $query->toBase()
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS pending', [ReorderSuggestionStatus::Pending->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS approved', [ReorderSuggestionStatus::Approved->value])
            ->selectRaw('COUNT(CASE WHEN status = ? AND urgency = ? THEN 1 END) AS urgent', [ReorderSuggestionStatus::Pending->value, ReorderUrgency::Critical->value])
            ->first();

        return [
            'pending' => (int) ($counts->pending ?? 0),
            'approved' => (int) ($counts->approved ?? 0),
            'urgent' => (int) ($counts->urgent ?? 0),
        ];
    }
}
