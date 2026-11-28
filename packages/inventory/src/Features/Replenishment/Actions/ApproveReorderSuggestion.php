<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Replenishment\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Enums\ReorderSuggestionStatus;
use AIArmada\Inventory\Features\Replenishment\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Approve a reorder suggestion.
 */
final class ApproveReorderSuggestion
{
    use AsAction;

    public function handle(InventoryReorderSuggestion $suggestion, ?string $userId = null): bool
    {
        return DB::transaction(function () use ($suggestion, $userId): bool {
            $query = InventoryReorderSuggestion::query()->whereKey($suggestion->getKey());

            if (InventoryOwnerScope::isEnabled()) {
                $owner = InventoryOwnerScope::resolveOwner();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $locked = $query->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReorderSuggestionStatus::Pending) {
                return false;
            }

            return $locked->update([
                'status' => ReorderSuggestionStatus::Approved,
                'approved_by' => $userId,
                'approved_at' => CarbonImmutable::now(),
            ]);
        });
    }
}
