<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\Priceable;
use AIArmada\Pricing\Actions\ApplyPromotionalAdjustment;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Services\PriceCalculator;
use AIArmada\Pricing\Support\CustomerPriceResolver;
use AIArmada\Pricing\Support\PromotionalPriceResolver;
use AIArmada\Pricing\Support\SegmentPriceResolver;
use AIArmada\Pricing\Support\TierResolver;
use AIArmada\Pricing\Tests\Concerns\EnsuresPricingSchema;
use Illuminate\Support\Facades\DB;

uses(EnsuresPricingSchema::class);

beforeEach(function (): void {
    $this->ensurePricingSchema();
});

class BatchPricingItem implements Priceable
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

it('matches per-line results and resolves the default list once', function (): void {
    $list = PriceList::create([
        'name' => 'Batch List',
        'slug' => 'batch-list-' . uniqid(),
        'currency' => 'MYR',
        'is_default' => true,
        'is_active' => true,
    ]);

    $lines = [];
    $expected = [];

    foreach (['batch-a', 'batch-b', 'batch-c'] as $index => $key) {
        $itemId = $key . '-' . uniqid();

        Price::create([
            'price_list_id' => $list->id,
            'priceable_type' => BatchPricingItem::class,
            'priceable_id' => $itemId,
            'amount' => 1000 * ($index + 1),
            'currency' => 'MYR',
        ]);

        $lines[] = ['item' => new BatchPricingItem($itemId, 9000), 'quantity' => 1];
        $expected[] = 1000 * ($index + 1);
    }

    DB::enableQueryLog();
    $results = $this->calculator->calculateMany($lines);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($results)->toHaveCount(3);

    foreach ($results as $index => $result) {
        expect($result->finalPrice)->toBe($expected[$index])
            ->and($result->discountSource)->toBe('Price List');
    }

    $defaultListQueries = array_values(array_filter(
        $queries,
        fn (array $query): bool => str_contains($query['query'], 'price_lists') && str_contains($query['query'], 'is_default'),
    ));

    expect($defaultListQueries)->toHaveCount(1);
});

it('resolves an explicit price list once in batch mode', function (): void {
    $list = PriceList::create([
        'name' => 'Explicit List',
        'slug' => 'explicit-list-' . uniqid(),
        'currency' => 'MYR',
        'is_default' => false,
        'is_active' => true,
    ]);

    $itemId = 'explicit-' . uniqid();

    Price::create([
        'price_list_id' => $list->id,
        'priceable_type' => BatchPricingItem::class,
        'priceable_id' => $itemId,
        'amount' => 2500,
        'currency' => 'MYR',
    ]);

    $item = new BatchPricingItem($itemId, 9000);
    DB::enableQueryLog();

    $results = $this->calculator->calculateMany(
        [['item' => $item], ['item' => $item], ['item' => $item]],
        ['price_list_id' => $list->id],
    );

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($results)->toHaveCount(3);

    foreach ($results as $result) {
        expect($result->finalPrice)->toBe(2500)
            ->and($result->priceListName)->toBe('Explicit List');
    }

    $listQueries = array_values(array_filter(
        $queries,
        fn (array $query): bool => str_contains($query['query'], 'price_lists'),
    ));

    expect($listQueries)->toHaveCount(1);
});

it('does not resolve price lists for empty batches', function (): void {
    DB::enableQueryLog();

    expect($this->calculator->calculateMany([]))->toBe([]);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBe([]);
});
