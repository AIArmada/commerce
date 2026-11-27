<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Actions;

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

        if ($purchase->status === 'paid') {
            return;
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
