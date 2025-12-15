<?php

declare(strict_types=1);

use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;

describe('PriceTier Model - Extended Tests', function (): void {
    describe('getTable', function (): void {
        it('returns configured table name', function (): void {
            $tier = new PriceTier;

            expect($tier->getTable())->toBe(config('pricing.tables.price_tiers', 'price_tiers'));
        });
    });

    describe('appliesTo', function (): void {
        it('returns true when quantity is within tier range', function (): void {
            $tier = new PriceTier([
                'min_quantity' => 10,
                'max_quantity' => 50,
                'amount' => 900,
            ]);

            expect($tier->appliesTo(10))->toBeTrue()
                ->and($tier->appliesTo(25))->toBeTrue()
                ->and($tier->appliesTo(50))->toBeTrue();
        });

        it('returns false when quantity is below minimum', function (): void {
            $tier = new PriceTier([
                'min_quantity' => 10,
                'max_quantity' => 50,
                'amount' => 900,
            ]);

            expect($tier->appliesTo(5))->toBeFalse()
                ->and($tier->appliesTo(9))->toBeFalse();
        });

        it('returns false when quantity exceeds maximum', function (): void {
            $tier = new PriceTier([
                'min_quantity' => 10,
                'max_quantity' => 50,
                'amount' => 900,
            ]);

            expect($tier->appliesTo(51))->toBeFalse()
                ->and($tier->appliesTo(100))->toBeFalse();
        });

        it('returns true for any quantity above min when max is null', function (): void {
            $tier = new PriceTier([
                'min_quantity' => 50,
                'max_quantity' => null,
                'amount' => 800,
            ]);

            expect($tier->appliesTo(50))->toBeTrue()
                ->and($tier->appliesTo(100))->toBeTrue()
                ->and($tier->appliesTo(1000))->toBeTrue();
        });
    });

    describe('getDescription', function (): void {
        it('returns range description when max_quantity is set', function (): void {
            $tier = new PriceTier([
                'min_quantity' => 10,
                'max_quantity' => 49,
                'amount' => 900,
            ]);

            expect($tier->getDescription())->toBe('10-49 units');
        });

        it('returns open-ended description when max_quantity is null', function (): void {
            $tier = new PriceTier([
                'min_quantity' => 50,
                'max_quantity' => null,
                'amount' => 800,
            ]);

            expect($tier->getDescription())->toBe('50+ units');
        });

        it('returns single value range when min equals max', function (): void {
            $tier = new PriceTier([
                'min_quantity' => 10,
                'max_quantity' => 10,
                'amount' => 900,
            ]);

            expect($tier->getDescription())->toBe('10-10 units');
        });
    });

    describe('getDiscountDescription', function (): void {
        // Note: These tests use model attributes directly without DB
        // The test database schema doesn't include discount_type/discount_value columns
        it('returns percentage discount description', function (): void {
            $tier = new PriceTier;
            $tier->min_quantity = 10;
            $tier->amount = 900;
            $tier->discount_type = 'percentage';
            $tier->discount_value = 10;

            expect($tier->getDiscountDescription())->toBe('10% off');
        });

        it('returns fixed discount description', function (): void {
            $tier = new PriceTier;
            $tier->min_quantity = 10;
            $tier->amount = 900;
            $tier->discount_type = 'fixed';
            $tier->discount_value = 500;

            expect($tier->getDiscountDescription())->toBe('RM 5.00 off');
        });

        it('returns null when no discount type', function (): void {
            $tier = new PriceTier([
                'min_quantity' => 10,
                'amount' => 900,
            ]);

            expect($tier->getDiscountDescription())->toBeNull();
        });

        it('returns null when no discount value', function (): void {
            $tier = new PriceTier;
            $tier->min_quantity = 10;
            $tier->amount = 900;
            $tier->discount_type = 'percentage';
            $tier->discount_value = null;

            expect($tier->getDiscountDescription())->toBeNull();
        });

        it('returns null for unknown discount type', function (): void {
            $tier = new PriceTier;
            $tier->min_quantity = 10;
            $tier->amount = 900;
            $tier->discount_type = 'unknown';
            $tier->discount_value = 10;

            expect($tier->getDiscountDescription())->toBeNull();
        });
    });

    describe('scopes', function (): void {
        it('can filter by quantity using forQuantity scope', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'tier-scope-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $prefix = uniqid();

            PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => "tier-test-{$prefix}",
                'min_quantity' => 1,
                'max_quantity' => 9,
                'amount' => 1000,
                'currency' => 'MYR',
            ]);

            PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => "tier-test-{$prefix}",
                'min_quantity' => 10,
                'max_quantity' => 49,
                'amount' => 900,
                'currency' => 'MYR',
            ]);

            PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => "tier-test-{$prefix}",
                'min_quantity' => 50,
                'max_quantity' => null,
                'amount' => 800,
                'currency' => 'MYR',
            ]);

            // Quantity 25 should match tiers with min_quantity <= 25 and max_quantity >= 25 or null
            $tiers = PriceTier::where('tierable_id', "tier-test-{$prefix}")
                ->forQuantity(25)
                ->get();

            expect($tiers)->toHaveCount(1)
                ->and($tiers->first()->min_quantity)->toBe(10);
        });

        it('can order tiers', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'tier-order-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $prefix = uniqid();

            PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => "tier-ord-{$prefix}",
                'min_quantity' => 50,
                'amount' => 800,
                'currency' => 'MYR',
            ]);

            PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => "tier-ord-{$prefix}",
                'min_quantity' => 10,
                'amount' => 900,
                'currency' => 'MYR',
            ]);

            PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => "tier-ord-{$prefix}",
                'min_quantity' => 1,
                'amount' => 1000,
                'currency' => 'MYR',
            ]);

            $tiers = PriceTier::where('tierable_id', "tier-ord-{$prefix}")
                ->ordered()
                ->get();

            expect($tiers)->toHaveCount(3)
                ->and($tiers->first()->min_quantity)->toBe(1)
                ->and($tiers->last()->min_quantity)->toBe(50);
        });
    });

    describe('relationships', function (): void {
        it('belongs to price list', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'tier-rel-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $tier = PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => 'tier-rel-' . uniqid(),
                'min_quantity' => 10,
                'amount' => 900,
                'currency' => 'MYR',
            ]);

            expect($tier->priceList)->toBeInstanceOf(PriceList::class)
                ->and($tier->priceList->id)->toBe($priceList->id);
        });

        it('has morphTo tierable relationship', function (): void {
            $tier = new PriceTier;

            // Just verify the relationship method returns correct type
            // Don't set tierable_type to avoid class resolution
            $relation = $tier->tierable();

            expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
        });
    });

    describe('default attributes', function (): void {
        it('has default min_quantity of 1', function (): void {
            $tier = new PriceTier;

            expect($tier->min_quantity)->toBe(1);
        });

        it('has default currency of MYR', function (): void {
            $tier = new PriceTier;

            expect($tier->currency)->toBe('MYR');
        });
    });

    describe('casts', function (): void {
        it('casts min_quantity to integer', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'tier-cast-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $tier = PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => 'tier-cast-' . uniqid(),
                'min_quantity' => '10',
                'amount' => 900,
                'currency' => 'MYR',
            ]);

            expect($tier->min_quantity)->toBeInt();
        });

        it('casts max_quantity to integer', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'tier-cast2-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $tier = PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => 'tier-cast2-' . uniqid(),
                'min_quantity' => 10,
                'max_quantity' => '50',
                'amount' => 900,
                'currency' => 'MYR',
            ]);

            expect($tier->max_quantity)->toBeInt();
        });

        it('casts amount to integer', function (): void {
            $priceList = PriceList::create([
                'name' => 'Test List',
                'slug' => 'tier-cast3-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $tier = PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => 'tier-cast3-' . uniqid(),
                'min_quantity' => 10,
                'amount' => '900',
                'currency' => 'MYR',
            ]);

            expect($tier->amount)->toBeInt();
        });
    });
});
