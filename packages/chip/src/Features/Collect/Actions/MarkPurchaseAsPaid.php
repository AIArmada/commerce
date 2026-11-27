<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Actions;

use AIArmada\Chip\Contracts\PurchaseStatus;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Features\Collect\Api\PurchaseMirror;
use AIArmada\Chip\Features\Collect\Models\Purchase;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Support\OwnerContext;

final readonly class MarkPurchaseAsPaid
{
    public function __construct(private ChipCollectService $chip, private PurchaseMirror $store) {}

    public function handle(string $purchaseId): void
    {
        $purchase = Purchase::query()->forOwner(OwnerContext::CURRENT, false)->findOrFail($purchaseId);

        if ($purchase->status === PurchaseStatus::PAID->value) {
            return;
        }

        if (in_array($purchase->status, [
            PurchaseStatus::REFUNDED->value,
            PurchaseStatus::PENDING_REFUND->value,
            PurchaseStatus::CHARGEBACK->value,
        ], true)) {
            throw new ChipValidationException('A refunded or reversed purchase cannot be marked as paid.');
        }

        $remote = $this->chip->markPurchaseAsPaid(
            $purchaseId,
            idempotencyKey: hash('sha256', 'offline-payment:' . $purchaseId),
        );

        if ($remote->id !== $purchaseId || $remote->status !== 'paid') {
            throw new ChipValidationException('CHIP did not confirm the requested purchase as paid.');
        }

        $this->store->storePurchasePayload($remote->toArray());
    }
}
