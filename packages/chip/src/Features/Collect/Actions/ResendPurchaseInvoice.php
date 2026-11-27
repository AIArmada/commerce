<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Actions;

use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Features\Collect\Models\Purchase;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Support\OwnerContext;

final readonly class ResendPurchaseInvoice
{
    public function __construct(private ChipCollectService $chip) {}

    public function handle(string $purchaseId): void
    {
        $purchase = Purchase::query()->forOwner(OwnerContext::CURRENT, false)->findOrFail($purchaseId);

        if (! is_string($purchase->client['email'] ?? null) || $purchase->client['email'] === '') {
            throw new ChipValidationException('The invoice has no recipient email.');
        }

        $this->chip->resendInvoice($purchaseId);
    }
}
