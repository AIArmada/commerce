<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Replenishment\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Enums\ReorderSuggestionStatus;
use AIArmada\Inventory\Features\Replenishment\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reject a reorder suggestion.
 */
final class RejectReorderSuggestion
{
    use AsAction;

    public function handle(InventoryReorderSuggestion $suggestion, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($suggestion, $reason): bool {
            $query = InventoryReorderSuggestion::query()->whereKey($suggestion->getKey());

            if (InventoryOwnerScope::isEnabled()) {
                $owner = InventoryOwnerScope::resolveOwner();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $locked = $query->lockForUpdate()->firstOrFail();

            if (! $locked->isActionable()) {
                return false;
            }

            $metadata = $locked->metadata ?? [];
            if ($reason) {
                $metadata['rejection_reason'] = $reason;
            }

            return $locked->update([
                'status' => ReorderSuggestionStatus::Rejected,
                'metadata' => $metadata,
            ]);
        });
    }
}
