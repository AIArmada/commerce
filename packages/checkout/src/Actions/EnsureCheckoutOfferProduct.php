<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Checkout\Data\CheckoutOfferProductData;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class EnsureCheckoutOfferProduct
{
    public function handle(CheckoutOfferProductData $offer): Product
    {
        return OwnerContext::withOwner(null, function () use ($offer): Product {
            $product = Product::query()->firstOrNew([
                'slug' => $offer->productSlug,
            ]);

            $product->fill([
                'name' => $offer->name,
                'short_description' => $offer->shortDescription,
                'description' => $offer->description,
                'sku' => $offer->sku,
                'type' => $offer->productType,
                'status' => $offer->productStatus,
                'visibility' => $offer->productVisibility,
                'price' => $this->basePriceForProduct($offer->priceAmount, $offer->compareAmount),
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

            if (! $product->exists && $product->published_at === null) {
                $product->published_at = Carbon::now();
            }

            if ($product->isDirty()) {
                $product->save();
            }

            $priceList = PriceList::query()->firstOrNew([
                'slug' => $offer->priceListSlug,
            ]);

            $priceList->fill([
                'name' => $offer->priceListName,
                'description' => $offer->priceListDescription,
                'currency' => $offer->currency,
                'priority' => $offer->priceListPriority,
                'is_default' => $offer->priceListIsDefault,
                'is_active' => $offer->priceListIsActive,
            ]);

            if ($priceList->isDirty()) {
                $priceList->save();
            }

            $price = Price::query()->firstOrNew([
                'price_list_id' => $priceList->getKey(),
                'priceable_type' => $product->getMorphClass(),
                'priceable_id' => $product->getKey(),
                'min_quantity' => 1,
            ]);

            $price->fill([
                'amount' => $offer->priceAmount,
                'compare_amount' => $offer->compareAmount,
                'currency' => $offer->currency,
            ]);

            if ($price->isDirty()) {
                $price->save();
            }

            $this->ensureInventory($product, $offer);

            return $product->fresh() ?? $product;
        });
    }

    private function basePriceForProduct(int $priceAmount, ?int $compareAmount): int
    {
        return $compareAmount !== null && $compareAmount > $priceAmount
            ? $compareAmount
            : $priceAmount;
    }

    private function ensureInventory(Product $product, CheckoutOfferProductData $offer): void
    {
        $minimumOnHand = max(0, $offer->minimumOnHand ?? 0);

        if ($minimumOnHand < 1 || ! config('checkout.integrations.inventory.enabled', false)) {
            return;
        }

        $inventoryLevelClass = 'AIArmada\\Inventory\\Models\\InventoryLevel';
        $inventoryServiceClass = 'AIArmada\\Inventory\\Services\\InventoryService';

        if (! class_exists($inventoryLevelClass) || ! class_exists($inventoryServiceClass)) {
            return;
        }

        /** @var class-string<Model> $inventoryLevelClass */
        /** @var InventoryService $inventoryService */
        $inventoryService = app($inventoryServiceClass);

        $inventoryLevelTable = (new $inventoryLevelClass)->getTable();

        if (! Schema::hasTable($inventoryLevelTable)) {
            return;
        }

        $hasInventoryLevels = $inventoryLevelClass::query()
            ->where('inventoryable_type', $product->getMorphClass())
            ->where('inventoryable_id', $product->getKey())
            ->exists();

        if ($hasInventoryLevels) {
            return;
        }

        $currentOnHand = $inventoryService->getTotalOnHand($product);

        if ($currentOnHand >= $minimumOnHand) {
            return;
        }

        $inventoryService->receiveAtDefault(
            model: $product,
            quantity: $minimumOnHand - $currentOnHand,
            reason: $offer->inventoryReason,
            note: $offer->inventoryNote,
        );
    }
}
