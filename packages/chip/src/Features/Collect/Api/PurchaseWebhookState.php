<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Api;

interface PurchaseWebhookState
{
    public function exists(string $purchaseId): bool;

    public function markPaid(string $purchaseId, ?int $totalMinor, ?string $paymentMethod, ?int $updatedOn): bool;

    public function markCancelled(string $purchaseId): bool;

    public function markFailed(string $purchaseId, string $reason): bool;
}
