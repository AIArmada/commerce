<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Replenishment\Actions;

use AIArmada\Inventory\Enums\ReorderSuggestionStatus;
use AIArmada\Inventory\Features\Replenishment\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class AdvanceReorderSuggestion
{
    public function markOrdered(InventoryReorderSuggestion $suggestion, string $orderId): bool
    {
        if (mb_trim($orderId) === '') {
            throw new InvalidArgumentException('Order ID must not be empty.');
        }

        return DB::transaction(function () use ($suggestion, $orderId): bool {
            $locked = $this->lock($suggestion);

            return $locked->status === ReorderSuggestionStatus::Approved && $locked->update([
                'status' => ReorderSuggestionStatus::Ordered,
                'order_id' => $orderId,
                'ordered_at' => CarbonImmutable::now(),
            ]);
        }, 3);
    }

    public function markReceived(InventoryReorderSuggestion $suggestion): bool
    {
        return DB::transaction(function () use ($suggestion): bool {
            $locked = $this->lock($suggestion);

            return $locked->status === ReorderSuggestionStatus::Ordered && $locked->update([
                'status' => ReorderSuggestionStatus::Received,
            ]);
        }, 3);
    }

    public function expire(InventoryReorderSuggestion $suggestion): bool
    {
        return DB::transaction(function () use ($suggestion): bool {
            $locked = $this->lock($suggestion);

            return $locked->isActionable() && $locked->update([
                'status' => ReorderSuggestionStatus::Expired,
            ]);
        }, 3);
    }

    private function lock(InventoryReorderSuggestion $suggestion): InventoryReorderSuggestion
    {
        return InventoryOwnerScope::forMutation(InventoryReorderSuggestion::query())
            ->whereKey($suggestion->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
