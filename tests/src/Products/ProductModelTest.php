<?php

declare(strict_types=1);

use AIArmada\Products\Models\Product;
use AIArmada\Products\Enums\ProductStatus;

describe('Product Model', function () {
    describe('Product Creation', function () {
        it('can create a product', function () {
            $product = Product::create([
                'name' => 'Test Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($product)->toBeInstanceOf(Product::class)
                ->and($product->name)->toBe('Test Product')
                ->and($product->price)->toBe(1000);
        });

        it('generates a slug automatically', function () {
            $product = Product::create([
                'name' => 'My Amazing Product',
                'price' => 2000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->slug)->toBe('my-amazing-product');
        });
    });

    describe('Product Status', function () {
        it('can check if product is active', function () {
            $active = Product::create([
                'name' => 'Active Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $draft = Product::create([
                'name' => 'Draft Product',
                'price' => 1000,
                'status' => ProductStatus::Draft,
            ]);

            expect($active->isActive())->toBeTrue()
                ->and($draft->isActive())->toBeFalse();
        });

        it('can check if product is draft', function () {
            $draft = Product::create([
                'name' => 'Draft Product',
                'price' => 1000,
                'status' => ProductStatus::Draft,
            ]);

            expect($draft->isDraft())->toBeTrue();
        });
    });

    describe('Product Types', function () {
        it('can be physical product', function () {
            $product = Product::create([
                'name' => 'Physical Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'type' => AIArmada\Products\Enums\ProductType::Simple,
            ]);

            expect($product->isPhysical())->toBeTrue();
        });

        it('can be digital product', function () {
            $product = Product::create([
                'name' => 'Digital Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'type' => AIArmada\Products\Enums\ProductType::Digital,
            ]);

            expect($product->isDigital())->toBeTrue();
        });
    });

    describe('Product Pricing', function () {
        it('can format price', function () {
            $product = Product::create([
                'name' => 'Priced Product',
                'price' => 1050,
                'currency' => 'MYR',
                'status' => ProductStatus::Active,
            ]);

            expect($product->getFormattedPrice())->toContain('1,050');
        });

        it('can check if product has discount', function () {
            $discounted = Product::create([
                'name' => 'Discounted Product',
                'price' => 800,
                'compare_price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $noDiscount = Product::create([
                'name' => 'Regular Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($discounted->hasDiscount())->toBeTrue()
                ->and($noDiscount->hasDiscount())->toBeFalse();
        });

        it('can calculate discount percentage', function () {
            $product = Product::create([
                'name' => 'Sale Product',
                'price' => 800,
                'compare_price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getDiscountPercentage())->toBe(20.0);
        });
    });

    describe('Product Scopes', function () {
        it('can filter active products', function () {
            Product::create(['name' => 'Active', 'price' => 1000, 'status' => ProductStatus::Active]);
            Product::create(['name' => 'Draft', 'price' => 1000, 'status' => ProductStatus::Draft]);
            Product::create(['name' => 'Archived', 'price' => 1000, 'status' => ProductStatus::Archived]);

            expect(Product::active()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter featured products', function () {
            Product::create(['name' => 'Featured', 'price' => 1000, 'status' => ProductStatus::Active, 'is_featured' => true]);
            Product::create(['name' => 'Not Featured', 'price' => 1000, 'status' => ProductStatus::Active, 'is_featured' => false]);

            expect(Product::featured()->count())->toBeGreaterThanOrEqual(1);
        });
    });

    describe('Product Profit Margin', function () {
        it('calculates profit margin when cost is set', function () {
            $product = Product::create([
                'name' => 'Margin Product',
                'price' => 1000,
                'cost' => 600,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getProfitMargin())->toBe(40.0);
        });

        it('returns null when cost is not set', function () {
            $product = Product::create([
                'name' => 'No Cost Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getProfitMargin())->toBeNull();
        });
    });

    describe('Product Soft Deletes', function () {
        it('can soft delete a product', function () {
            $product = Product::create([
                'name' => 'To Delete',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $id = $product->id;
            $product->delete();

            expect(Product::find($id))->toBeNull()
                ->and(Product::withTrashed()->find($id))->not->toBeNull();
        });
    });
});
