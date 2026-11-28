<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Checkout\Data\CheckoutOfferProductData;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Features\Stock\Services\InventoryService;
use AIArmada\Pricing\Actions\EnsureListPrice;
use AIArmada\Pricing\Actions\EnsurePriceListBySlug;
use AIArmada\Products\Actions\EnsureProductBySlug;
use AIArmada\Products\Models\Product;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class EnsureCheckoutOfferProduct
{
    public function __construct(
        private EnsureProductBySlug $ensureProduct,
        private EnsurePriceListBySlug $ensurePriceList,
        private EnsureListPrice $ensureListPrice,
    ) {}

    public function handle(CheckoutOfferProductData $offer): Product
    {
        if (! OwnerContext::isExplicitGlobal()) {
            throw new AuthorizationException(
                'Ensuring a checkout offer product requires an explicit global owner context.',
            );
        }

        return DB::transaction(function () use ($offer): Product {
            $product = $this->ensureProduct->execute($offer->productSlug, [
                'name' => $offer->name,
                'short_description' => $offer->shortDescription,
                'description' => $offer->description,
                'sku' => $offer->sku,
                'type' => $offer->productType,
                'status' => $offer->productStatus,
                'visibility' => $offer->productVisibility,
                'price' => $offer->priceAmount,
                'compare_price' => $offer->compareAmount,
                'currency' => $offer->currency,
                'is_featured' => $offer->isFeatured,
                'is_taxable' => $offer->isTaxable,
                'requires_shipping' => $offer->requiresShipping,
                'supports_variants' => $offer->supportsVariants ?? $offer->productType->supportsVariantsByDefault(),
                'tracks_inventory' => $offer->tracksInventory ?? $offer->productType->tracksInventoryByDefault(),
                'meta_title' => $offer->metaTitle,
                'meta_description' => $offer->metaDescription,
                'metadata' => $offer->metadata,
            ]);

            $priceList = $this->ensurePriceList->execute($offer->priceListSlug, [
                'name' => $offer->priceListName,
                'description' => $offer->priceListDescription,
                'currency' => $offer->currency,
                'priority' => $offer->priceListPriority,
                'is_default' => $offer->priceListIsDefault,
                'is_active' => $offer->priceListIsActive,
            ]);

            $this->ensureListPrice->execute(
                $priceList,
                $product->getMorphClass(),
                (string) $product->getKey(),
                $offer->priceAmount,
                $offer->compareAmount,
                $offer->currency,
            );

            if (($offer->minimumOnHand ?? 0) > 0 && config('checkout.integrations.inventory.enabled', false) && class_exists(InventoryService::class)) {
                app(InventoryService::class)->bootstrapAtDefault($product, $offer->minimumOnHand, $offer->inventoryReason, $offer->inventoryNote);
            }

            return $product->fresh() ?? $product;
        });
    }
}
