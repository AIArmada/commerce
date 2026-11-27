<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Actions;

use AIArmada\Chip\Contracts\PurchaseStatus;
use AIArmada\Chip\Features\Collect\Api\PurchaseWebhookState;
use AIArmada\Chip\Features\Collect\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\DB;

final readonly class ApplyPurchaseWebhookState implements PurchaseWebhookState
{
    public function exists(string $purchaseId): bool
    {
        return Purchase::query()->forOwner(OwnerContext::CURRENT, false)->whereKey($purchaseId)->exists();
    }

    public function markPaid(string $purchaseId, ?int $totalMinor, ?string $paymentMethod, ?int $updatedOn): bool
    {
        return DB::transaction(function () use ($purchaseId, $totalMinor, $paymentMethod, $updatedOn): bool {
            $purchase = Purchase::query()->forOwner(OwnerContext::CURRENT, false)->whereKey($purchaseId)->lockForUpdate()->first();

            if (! $purchase instanceof Purchase || in_array($purchase->status, [
                PurchaseStatus::REFUNDED->value,
                PurchaseStatus::PENDING_REFUND->value,
                PurchaseStatus::CHARGEBACK->value,
                PurchaseStatus::CLEARED->value,
                PurchaseStatus::SETTLED->value,
            ], true)) {
                return false;
            }

            $previousUpdate = $purchase->getRawOriginal('updated_on');

            if ($updatedOn !== null && is_numeric($previousUpdate) && $updatedOn < (int) $previousUpdate) {
                return false;
            }

            $purchase->forceFill([
                'status' => PurchaseStatus::PAID->value,
                'total_minor' => $totalMinor ?? $purchase->total_minor,
                'payment_method' => $paymentMethod ?? $purchase->payment_method,
                'updated_on' => $updatedOn ?? $previousUpdate,
            ])->save();

            return true;
        });
    }

    public function markCancelled(string $purchaseId): bool
    {
        return $this->markUnsuccessful($purchaseId, PurchaseStatus::CANCELLED);
    }

    public function markFailed(string $purchaseId, string $reason): bool
    {
        return $this->markUnsuccessful($purchaseId, PurchaseStatus::ERROR, $reason);
    }

    private function markUnsuccessful(string $purchaseId, PurchaseStatus $status, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($purchaseId, $status, $reason): bool {
            $purchase = Purchase::query()->forOwner(OwnerContext::CURRENT, false)->whereKey($purchaseId)->lockForUpdate()->first();

            if (! $purchase instanceof Purchase || in_array($purchase->status, [
                PurchaseStatus::PAID->value,
                PurchaseStatus::CLEARED->value,
                PurchaseStatus::SETTLED->value,
                PurchaseStatus::PENDING_REFUND->value,
                PurchaseStatus::REFUNDED->value,
                PurchaseStatus::CHARGEBACK->value,
            ], true)) {
                return false;
            }

            $attributes = ['status' => $status->value];

            if ($reason !== null) {
                $attributes['failure_reason'] = $reason;
            }

            $purchase->forceFill($attributes)->save();

            return true;
        });
    }
}
