<?php

declare(strict_types=1);

use AIArmada\Pricing\Actions\ApplyPromotionalAdjustment;
use AIArmada\Pricing\Contracts\Priceable;
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

it('keeps explicit price lists per line in batch mode', function (): void {
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

    $results = $this->calculator->calculateMany(
        [['item' => new BatchPricingItem($itemId, 9000)]],
        ['price_list_id' => $list->id],
    );

    expect($results[0]->finalPrice)->toBe(2500)
        ->and($results[0]->priceListName)->toBe('Explicit List');
});
