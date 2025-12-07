<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions\Purchases;

use AIArmada\Chip\Data\Purchase;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a purchase via CHIP payment gateway.
 */
final class CreatePurchase
{
    use AsAction;

    public function __construct(
        private readonly PurchasesApi $purchasesApi,
    ) {}

    /**
     * Create a new purchase.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Purchase
    {
        return $this->purchasesApi->create($data);
    }
}
