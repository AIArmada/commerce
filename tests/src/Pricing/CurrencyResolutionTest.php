<?php

declare(strict_types=1);

use AIArmada\Pricing\Actions\ApplyPromotionalAdjustment;
use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Pricing\Services\PriceCalculator;
use AIArmada\Pricing\Support\CustomerPriceResolver;
use AIArmada\Pricing\Support\PromotionalPriceResolver;
use AIArmada\Pricing\Support\SegmentPriceResolver;
use AIArmada\Pricing\Support\TierResolver;
use AIArmada\Pricing\Tests\Concerns\EnsuresPricingSchema;

uses(EnsuresPricingSchema::class);

beforeEach(function (): void {
    $this->ensurePricingSchema();
});

class CurrencyResolutionItem implements Priceable
{
    public function __construct(
        private string $id,
        private int $basePrice,
    ) {}

    public function getBuyableIdentifier(): string
    {
        return $this->id;
    }

    public function getBasePrice(): int
    {
        return $this->basePrice;
    }

    public function getComparePrice(): ?int
    {
        return null;
    }

    public function isOnSale(): bool
    {
        return false;
    }

    public function getDiscountPercentage(): ?float
    {
        return null;
    }
}

beforeEach(function (): void {
    $this->calculator = new PriceCalculator(
        new TierResolver,
        new PromotionalPriceResolver(new ApplyPromotionalAdjustment),
        new CustomerPriceResolver,
        new SegmentPriceResolver,
    );
});

function createCurrencyList(string $currency, bool $isDefault = true, array $overrides = []): PriceList
{
    return PriceList::create(array_merge([
        'name' => 'List ' . $currency . ' ' . uniqid(),
        'slug' => 'list-' . mb_strtolower($currency) . '-' . uniqid(),
        'currency' => $currency,
        'is_default' => $isDefault,
        'is_active' => true,
    ], $overrides));
}

it('ignores the default list when its currency differs', function (): void {
    $itemId = 'currency-item-' . uniqid();
    $list = createCurrencyList('USD');

    Price::create([
        'price_list_id' => $list->id,
        'priceable_type' => CurrencyResolutionItem::class,
        'priceable_id' => $itemId,
        'amount' => 500,
        'currency' => 'USD',
    ]);

    $item = new CurrencyResolutionItem($itemId, 10000);
    $result = $this->calculator->calculate($item, 1, ['currency' => 'MYR']);

    expect($result->finalPrice)->toBe(10000)
        ->and($result->discountSource)->toBeNull();
});

it('applies the default list when its currency matches', function (): void {
    $itemId = 'currency-item-' . uniqid();
    $list = createCurrencyList('USD');

    Price::create([
        'price_list_id' => $list->id,
        'priceable_type' => CurrencyResolutionItem::class,
        'priceable_id' => $itemId,
        'amount' => 500,
        'currency' => 'USD',
    ]);

    $item = new CurrencyResolutionItem($itemId, 10000);
    $result = $this->calculator->calculate($item, 1, ['currency' => 'USD']);

    expect($result->finalPrice)->toBe(500)
        ->and($result->discountSource)->toBe('Price List')
        ->and($result->currency)->toBe('USD');
});

it('ignores prices whose currency differs from their list', function (): void {
    $itemId = 'currency-item-' . uniqid();
    $list = createCurrencyList('MYR');

    Price::create([
        'price_list_id' => $list->id,
        'priceable_type' => CurrencyResolutionItem::class,
        'priceable_id' => $itemId,
        'amount' => 500,
        'currency' => 'USD',
    ]);

    $item = new CurrencyResolutionItem($itemId, 10000);
    $result = $this->calculator->calculate($item);

    expect($result->finalPrice)->toBe(10000);
});

it('ignores customer prices in another currency', function (): void {
    $itemId = 'currency-item-' . uniqid();
    $customerId = 'cust-' . uniqid();
    $list = createCurrencyList('USD', false, ['customer_id' => $customerId]);

    Price::create([
        'price_list_id' => $list->id,
        'priceable_type' => CurrencyResolutionItem::class,
        'priceable_id' => $itemId,
        'amount' => 500,
        'currency' => 'USD',
    ]);

    $item = new CurrencyResolutionItem($itemId, 10000);
    $result = $this->calculator->calculate($item, 1, ['customer_id' => $customerId, 'currency' => 'MYR']);

    expect($result->finalPrice)->toBe(10000);
});

it('ignores segment prices in another currency', function (): void {
    $itemId = 'currency-item-' . uniqid();
    $segmentId = 'seg-' . uniqid();
    $list = createCurrencyList('USD', false, ['segment_id' => $segmentId]);

    Price::create([
        'price_list_id' => $list->id,
        'priceable_type' => CurrencyResolutionItem::class,
        'priceable_id' => $itemId,
        'amount' => 500,
        'currency' => 'USD',
    ]);

    $item = new CurrencyResolutionItem($itemId, 10000);
    $result = $this->calculator->calculate($item, 1, ['segment_ids' => [$segmentId], 'currency' => 'MYR']);

    expect($result->finalPrice)->toBe(10000);
});

it('ignores tiers in another currency', function (): void {
    $itemId = 'currency-item-' . uniqid();

    PriceTier::create([
        'tierable_type' => CurrencyResolutionItem::class,
        'tierable_id' => $itemId,
        'min_quantity' => 5,
        'amount' => 500,
        'currency' => 'USD',
        'is_active' => true,
    ]);

    $item = new CurrencyResolutionItem($itemId, 10000);
    $result = $this->calculator->calculate($item, 10, ['currency' => 'MYR']);

    expect($result->finalPrice)->toBe(10000);
});

it('ignores an explicit price list in another currency', function (): void {
    $itemId = 'currency-item-' . uniqid();
    $list = createCurrencyList('USD', false);

    Price::create([
        'price_list_id' => $list->id,
        'priceable_type' => CurrencyResolutionItem::class,
        'priceable_id' => $itemId,
        'amount' => 500,
        'currency' => 'USD',
    ]);

    $item = new CurrencyResolutionItem($itemId, 10000);
    $result = $this->calculator->calculate($item, 1, ['price_list_id' => $list->id, 'currency' => 'MYR']);

    expect($result->finalPrice)->toBe(10000);
});
