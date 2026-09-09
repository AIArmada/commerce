<?php

declare(strict_types=1);

use AIArmada\Pricing\Actions\ApplyPromotionalAdjustment;
use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Services\PriceCalculator;
use AIArmada\Pricing\Support\CustomerPriceResolver;
use AIArmada\Pricing\Support\PromotionalPriceResolver;
use AIArmada\Pricing\Support\ResolvesEffectiveAt;
use AIArmada\Pricing\Support\SegmentPriceResolver;
use AIArmada\Pricing\Support\TierResolver;
use AIArmada\Pricing\Tests\Concerns\EnsuresPricingSchema;
use Carbon\CarbonImmutable;

uses(EnsuresPricingSchema::class);

beforeEach(function (): void {
    $this->ensurePricingSchema();
});

beforeEach(function (): void {
    config(['pricing.features.owner.enabled' => false]);
});

it('treats deactivated price lists and prices as inactive', function (): void {
    $priceList = PriceList::create([
        'name' => 'Deactivated List',
        'slug' => 'deactivated-list-' . uniqid(),
        'currency' => 'MYR',
        'deactivated_at' => CarbonImmutable::now()->subMinute(),
    ]);

    expect($priceList->isActive())->toBeFalse()
        ->and(PriceList::query()->active()->whereKey($priceList->id)->exists())->toBeFalse();

    $activeList = PriceList::create([
        'name' => 'Active List',
        'slug' => 'active-list-' . uniqid(),
        'currency' => 'MYR',
    ]);
    $price = Price::create([
        'price_list_id' => $activeList->id,
        'priceable_type' => 'TestProduct',
        'priceable_id' => 'deactivated-price-' . uniqid(),
        'amount' => 5000,
        'currency' => 'MYR',
        'deactivated_at' => CarbonImmutable::now()->subMinute(),
    ]);

    expect($price->isActive())->toBeFalse()
        ->and(Price::query()->active()->whereKey($price->id)->exists())->toBeFalse();
});

it('excludes deactivated lists and prices during calculator resolution', function (): void {
    $item = new class implements Priceable
    {
        public function getBuyableIdentifier(): string
        {
            return 'deactivated-resolution-item';
        }

        public function getBasePrice(): int
        {
            return 10000;
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
    };

    $deactivatedList = PriceList::create([
        'name' => 'Deactivated Resolution List',
        'slug' => 'deactivated-resolution-list-' . uniqid(),
        'currency' => 'MYR',
        'deactivated_at' => CarbonImmutable::now()->subMinute(),
    ]);
    Price::create([
        'price_list_id' => $deactivatedList->id,
        'priceable_type' => $item::class,
        'priceable_id' => $item->getBuyableIdentifier(),
        'amount' => 5000,
        'currency' => 'MYR',
    ]);

    $activeList = PriceList::create([
        'name' => 'Deactivated Resolution Price List',
        'slug' => 'deactivated-resolution-price-list-' . uniqid(),
        'currency' => 'MYR',
    ]);
    Price::create([
        'price_list_id' => $activeList->id,
        'priceable_type' => $item::class,
        'priceable_id' => $item->getBuyableIdentifier(),
        'amount' => 4000,
        'currency' => 'MYR',
        'deactivated_at' => CarbonImmutable::now()->subMinute(),
    ]);

    $calculator = new PriceCalculator(
        new TierResolver,
        new PromotionalPriceResolver(new ApplyPromotionalAdjustment),
        new CustomerPriceResolver,
        new SegmentPriceResolver,
    );

    expect($calculator->calculate($item, 1, ['price_list_id' => $deactivatedList->id])->finalPrice)->toBe(10000)
        ->and($calculator->calculate($item, 1, ['price_list_id' => $activeList->id])->finalPrice)->toBe(10000);
});

it('keeps exactly one default list by making the latest saved default the winner', function (): void {
    $first = PriceList::create([
        'name' => 'First Default',
        'slug' => 'first-default-' . uniqid(),
        'currency' => 'MYR',
        'is_default' => true,
    ]);
    $second = PriceList::create([
        'name' => 'Second Default',
        'slug' => 'second-default-' . uniqid(),
        'currency' => 'MYR',
        'is_default' => true,
    ]);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        ->and(PriceList::query()->default()->get())->toHaveCount(1);
});

it('resolves legacy duplicate defaults deterministically by priority', function (): void {
    [$lowerPriority, $higherPriority] = PriceList::withoutEvents(static function (): array {
        return [
            PriceList::create([
                'name' => 'Legacy Lower Priority',
                'slug' => 'legacy-lower-' . uniqid(),
                'currency' => 'MYR',
                'priority' => 1,
                'is_default' => true,
            ]),
            PriceList::create([
                'name' => 'Legacy Higher Priority',
                'slug' => 'legacy-higher-' . uniqid(),
                'currency' => 'MYR',
                'priority' => 10,
                'is_default' => true,
            ]),
        ];
    });

    $item = new class implements Priceable
    {
        public function getBuyableIdentifier(): string
        {
            return 'legacy-default-item';
        }

        public function getBasePrice(): int
        {
            return 10000;
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
    };

    Price::create([
        'price_list_id' => $lowerPriority->id,
        'priceable_type' => $item::class,
        'priceable_id' => $item->getBuyableIdentifier(),
        'amount' => 9000,
        'currency' => 'MYR',
    ]);
    Price::create([
        'price_list_id' => $higherPriority->id,
        'priceable_type' => $item::class,
        'priceable_id' => $item->getBuyableIdentifier(),
        'amount' => 8000,
        'currency' => 'MYR',
    ]);

    $calculator = new PriceCalculator(
        new TierResolver,
        new PromotionalPriceResolver(new ApplyPromotionalAdjustment),
        new CustomerPriceResolver,
        new SegmentPriceResolver,
    );

    expect($calculator->calculate($item)->finalPrice)->toBe(8000);
});

it('uses one effective-at parser across pricing resolvers', function (): void {
    $parser = new class
    {
        use ResolvesEffectiveAt;

        /**
         * @param  array<string, mixed>  $context
         */
        public function parse(array $context): CarbonImmutable
        {
            return $this->resolveEffectiveAt($context);
        }
    };

    $effectiveAt = CarbonImmutable::parse('2025-01-04 10:00:00');

    expect($parser->parse(['effective_at' => $effectiveAt]))->toEqual($effectiveAt)
        ->and($parser->parse(['effective_at' => $effectiveAt->toIso8601String()]))->toEqual($effectiveAt)
        ->and($parser->parse(['effective_at' => $effectiveAt->getTimestamp()]))->toEqual($effectiveAt);
});
