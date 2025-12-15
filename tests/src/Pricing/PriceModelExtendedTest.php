<?php

declare(strict_types=1);

use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Support\Carbon;

describe('Price Model - Extended Tests', function (): void {
    describe('getTable', function (): void {
        it('returns configured table name', function (): void {
            $price = new Price;

            expect($price->getTable())->toBe(config('pricing.tables.prices', 'prices'));
        });
    });

    describe('isActive', function (): void {
        it('returns true when price has no date restrictions', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-' . uniqid(),
                'amount' => 5000,
                'currency' => 'MYR',
            ]);

            expect($price->isActive())->toBeTrue();
        });

        it('returns true when within date range', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-active-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-active-' . uniqid(),
                'amount' => 5000,
                'currency' => 'MYR',
                'starts_at' => Carbon::now()->subDay(),
                'ends_at' => Carbon::now()->addDay(),
            ]);

            expect($price->isActive())->toBeTrue();
        });

        it('returns false when before start date', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-future-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-future-' . uniqid(),
                'amount' => 5000,
                'currency' => 'MYR',
                'starts_at' => Carbon::now()->addDay(),
            ]);

            expect($price->isActive())->toBeFalse();
        });

        it('returns false when after end date', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-expired-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-expired-' . uniqid(),
                'amount' => 5000,
                'currency' => 'MYR',
                'starts_at' => Carbon::now()->subWeek(),
                'ends_at' => Carbon::now()->subDay(),
            ]);

            expect($price->isActive())->toBeFalse();
        });
    });

    describe('hasDiscount', function (): void {
        it('returns true when compare_amount is greater than amount', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-disc-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-disc-' . uniqid(),
                'amount' => 8000,
                'compare_amount' => 10000,
                'currency' => 'MYR',
            ]);

            expect($price->hasDiscount())->toBeTrue();
        });

        it('returns false when no compare_amount', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-no-disc-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-no-disc-' . uniqid(),
                'amount' => 8000,
                'currency' => 'MYR',
            ]);

            expect($price->hasDiscount())->toBeFalse();
        });

        it('returns false when compare_amount equals amount', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-eq-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-eq-' . uniqid(),
                'amount' => 8000,
                'compare_amount' => 8000,
                'currency' => 'MYR',
            ]);

            expect($price->hasDiscount())->toBeFalse();
        });
    });

    describe('getDiscountPercentage', function (): void {
        it('returns percentage when on discount', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-pct-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-pct-' . uniqid(),
                'amount' => 8000,
                'compare_amount' => 10000,
                'currency' => 'MYR',
            ]);

            expect($price->getDiscountPercentage())->toBe(20.0);
        });

        it('returns null when not on discount', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-no-pct-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-no-pct-' . uniqid(),
                'amount' => 8000,
                'currency' => 'MYR',
            ]);

            expect($price->getDiscountPercentage())->toBeNull();
        });
    });

    describe('getFormattedAmount', function (): void {
        it('formats MYR correctly', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-fmt-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-fmt-' . uniqid(),
                'amount' => 9999,
                'currency' => 'MYR',
            ]);

            expect($price->getFormattedAmount())->toBe('RM99.99');
        });

        it('formats USD correctly', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-usd-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-usd-' . uniqid(),
                'amount' => 5000,
                'currency' => 'USD',
            ]);

            expect($price->getFormattedAmount())->toBe('$50.00');
        });

        it('formats SGD correctly', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-sgd-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-sgd-' . uniqid(),
                'amount' => 3000,
                'currency' => 'SGD',
            ]);

            expect($price->getFormattedAmount())->toBe('S$30.00');
        });

        it('formats unknown currency with code', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'test-thb-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'test-thb-' . uniqid(),
                'amount' => 10000,
                'currency' => 'THB',
            ]);

            expect($price->getFormattedAmount())->toBe('THB 100.00');
        });
    });

    describe('scopes', function (): void {
        it('can filter active prices', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'scope-test-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $prefix = uniqid();

            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => "active-{$prefix}",
                'amount' => 5000,
                'currency' => 'MYR',
            ]);

            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => "future-{$prefix}",
                'amount' => 5000,
                'currency' => 'MYR',
                'starts_at' => Carbon::now()->addWeek(),
            ]);

            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => "expired-{$prefix}",
                'amount' => 5000,
                'currency' => 'MYR',
                'ends_at' => Carbon::now()->subDay(),
            ]);

            $activePrices = Price::where('priceable_id', 'like', "%-{$prefix}")->active()->get();

            expect($activePrices)->toHaveCount(1)
                ->and($activePrices->first()->priceable_id)->toBe("active-{$prefix}");
        });

        it('can filter by quantity', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'qty-test-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $prefix = uniqid();

            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => "qty-{$prefix}",
                'amount' => 5000,
                'min_quantity' => 1,
                'currency' => 'MYR',
            ]);

            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => "qty-bulk-{$prefix}",
                'amount' => 4000,
                'min_quantity' => 10,
                'currency' => 'MYR',
            ]);

            $prices = Price::where('priceable_id', 'like', "qty%-{$prefix}")->forQuantity(5)->get();

            expect($prices)->toHaveCount(1)
                ->and($prices->first()->min_quantity)->toBe(1);
        });
    });

    describe('relationships', function (): void {
        it('belongs to price list', function (): void {
            $priceList = PriceList::create([
                'name' => 'Related List',
                'slug' => 'related-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $price = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'related-' . uniqid(),
                'amount' => 5000,
                'currency' => 'MYR',
            ]);

            expect($price->priceList)->toBeInstanceOf(PriceList::class)
                ->and($price->priceList->id)->toBe($priceList->id);
        });
    });

    describe('default attributes', function (): void {
        it('has default min_quantity of 1', function (): void {
            $price = new Price;

            expect($price->min_quantity)->toBe(1);
        });

        it('has default currency of MYR', function (): void {
            $price = new Price;

            expect($price->currency)->toBe('MYR');
        });
    });
});
