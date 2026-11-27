<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Replenishment\Actions;

use AIArmada\Inventory\Features\Replenishment\Models\InventoryReorderSuggestion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reject a reorder suggestion.
 */
final class RejectReorderSuggestion
{
    use AsAction;

    public function handle(InventoryReorderSuggestion $suggestion, ?string $reason = null): bool
    {
        return $suggestion->reject($reason);
    }
}
