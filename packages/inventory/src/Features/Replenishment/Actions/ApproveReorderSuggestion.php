<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Replenishment\Actions;

use AIArmada\Inventory\Features\Replenishment\Models\InventoryReorderSuggestion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Approve a reorder suggestion.
 */
final class ApproveReorderSuggestion
{
    use AsAction;

    public function handle(InventoryReorderSuggestion $suggestion, ?string $userId = null): bool
    {
        return $suggestion->approve($userId);
    }
}
