<?php

declare(strict_types=1);

use AIArmada\Checkout\Actions\EnsureCheckoutOfferProduct;
use AIArmada\Checkout\Data\CheckoutOfferProductData;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('creates a checkout offer product with price list, price, and seeded inventory', function (): void {
    config()->set('checkout.integrations.inventory.enabled', true);

    $suffix = Str::lower(Str::random(8));
    $offer = new CheckoutOfferProductData(
        productSlug: 'checkout-offer-' . $suffix,
        priceListSlug: 'checkout-offer-' . $suffix . '-public',
        name: 'Checkout Offer ' . $suffix,
        description: 'Reusable checkout offer bootstrap test product.',
        sku: 'offer-' . $suffix,
        priceAmount: 9700,
        currency: 'MYR',
        priceListName: 'Checkout Offer ' . $suffix . ' Public',
        shortDescription: 'Short offer summary',
        compareAmount: 12700,
        metaTitle: 'Checkout Offer Title',
        metaDescription: 'Checkout offer meta description',
        metadata: [
            'source' => 'test',
            'offer_slug' => $suffix,
        ],
        priceListDescription: 'Public checkout pricing for the reusable offer.',
        supportsVariants: true,
        tracksInventory: true,
        minimumOnHand: 5,
        inventoryReason: 'checkout-offer-test',
        inventoryNote: 'Seeded by checkout offer bootstrap test.',
    );

    $product = app(EnsureCheckoutOfferProduct::class)->handle($offer);

    $priceList = OwnerContext::withOwner(null, static fn (): ?PriceList => PriceList::query()
        ->where('slug', $offer->priceListSlug)
        ->first());
    $price = OwnerContext::withOwner(null, fn (): ?Price => Price::query()
        ->where('price_list_id', $priceList?->getKey())
        ->where('priceable_type', $product->getMorphClass())
        ->where('priceable_id', $product->getKey())
        ->first());
    expect($product->slug)->toBe($offer->productSlug)
        ->and($product->sku)->toBe($offer->sku)
        ->and($product->price)->toBe(12700)
        ->and($product->compare_price)->toBe(12700)
        ->and($product->supportsVariants())->toBeTrue()
        ->and($product->tracksInventory())->toBeTrue()
        ->and($product->requires_shipping)->toBeFalse()
        ->and($priceList?->name)->toBe($offer->priceListName)
        ->and($price?->amount)->toBe(9700)
        ->and($price?->compare_amount)->toBe(12700);

    if (Schema::hasTable((new InventoryLevel)->getTable())) {
        $onHand = OwnerContext::withOwner(null, static fn (): int => app(InventoryService::class)->getTotalOnHand($product));

        expect($onHand)->toBeGreaterThanOrEqual(5);
    }
});

it('is idempotent when ensuring the same checkout offer product twice', function (): void {
    $suffix = Str::lower(Str::random(8));
    $offer = new CheckoutOfferProductData(
        productSlug: 'checkout-offer-idempotent-' . $suffix,
        priceListSlug: 'checkout-offer-idempotent-' . $suffix . '-public',
        name: 'Checkout Offer Idempotent ' . $suffix,
        description: 'Idempotent checkout offer bootstrap test product.',
        sku: 'offer-idempotent-' . $suffix,
        priceAmount: 9700,
        currency: 'MYR',
        priceListName: 'Checkout Offer Idempotent ' . $suffix,
    );

    $firstProduct = app(EnsureCheckoutOfferProduct::class)->handle($offer);
    $secondProduct = app(EnsureCheckoutOfferProduct::class)->handle($offer);

    $productCount = OwnerContext::withOwner(null, static fn (): int => Product::query()->where('slug', $offer->productSlug)->count());
    $priceListCount = OwnerContext::withOwner(null, static fn (): int => PriceList::query()->where('slug', $offer->priceListSlug)->count());
    $priceCount = OwnerContext::withOwner(null, fn (): int => Price::query()
        ->where('price_list_id', PriceList::query()->where('slug', $offer->priceListSlug)->value('id'))
        ->where('priceable_type', $firstProduct->getMorphClass())
        ->where('priceable_id', $firstProduct->getKey())
        ->count());

    expect($secondProduct->is($firstProduct))->toBeTrue()
        ->and($productCount)->toBe(1)
        ->and($priceListCount)->toBe(1)
        ->and($priceCount)->toBe(1)
        ->and($secondProduct->supportsVariants())->toBeFalse()
        ->and($secondProduct->tracksInventory())->toBeFalse();
});
