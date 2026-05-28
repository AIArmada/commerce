<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use Spatie\LaravelData\Data;

final class CheckoutOfferProductData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $productSlug,
        public readonly string $priceListSlug,
        public readonly string $name,
        public readonly string $description,
        public readonly string $sku,
        public readonly int $priceAmount,
        public readonly string $currency,
        public readonly string $priceListName,
        public readonly ?string $shortDescription = null,
        public readonly ?int $compareAmount = null,
        public readonly ?string $metaTitle = null,
        public readonly ?string $metaDescription = null,
        public readonly array $metadata = [],
        public readonly ?string $priceListDescription = null,
        public readonly int $priceListPriority = 100,
        public readonly bool $priceListIsDefault = true,
        public readonly bool $priceListIsActive = true,
        public readonly bool $isFeatured = true,
        public readonly bool $isTaxable = false,
        public readonly bool $requiresShipping = false,
        public readonly ?bool $supportsVariants = null,
        public readonly ?bool $tracksInventory = null,
        public readonly ProductType $productType = ProductType::Digital,
        public readonly ProductStatus $productStatus = ProductStatus::Active,
        public readonly ProductVisibility $productVisibility = ProductVisibility::Individual,
        public readonly ?int $minimumOnHand = null,
        public readonly string $inventoryReason = 'checkout-offer-bootstrap',
        public readonly ?string $inventoryNote = null,
    ) {}
}
