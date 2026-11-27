<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions\Purchases;

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Features\Collect\Api\PurchaseOperations;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a purchase via CHIP payment gateway.
 */
final class CreatePurchase
{
    use AsAction;

    public function __construct(
        private readonly PurchaseOperations $purchasesApi,
    ) {}

    /**
     * Create a new purchase.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): PurchaseData
    {
        return $this->purchasesApi->create($data);
    }
}
