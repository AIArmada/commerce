<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Api;

interface PurchaseMirror
{
    /** @param array<string, mixed> $payload */
    public function storePurchasePayload(array $payload): void;

    /** @param array<string, mixed> $payload */
    public function storePayment(array $payload): void;
}
