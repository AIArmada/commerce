<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only products and categories for the current owner context', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    [$categoryA, $productA] = OwnerContext::withOwner($ownerA, function (): array {
        $category = Category::create([
            'name' => 'Smartphones',
        ]);

        $product = Product::create([
            'name' => 'iPhone 15 Pro',
            'sku' => 'IP15-PRO-001',
            'price' => 539900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);

        $product->categories()->attach($category);

        return [$category, $product];
    });

    [$categoryB, $productB] = OwnerContext::withOwner($ownerB, function (): array {
        $category = Category::create([
            'name' => 'Shoes',
        ]);

        $product = Product::create([
            'name' => 'Nike Air Jordan 1',
            'sku' => 'AJ1-001',
            'price' => 45900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);

        $product->categories()->attach($category);

        return [$category, $product];
    });

    OwnerContext::withOwner($ownerA, function () use ($categoryA, $productA, $categoryB, $productB): void {
        $this->get('/products')
            ->assertOk()
            ->assertSee($productA->name)
            ->assertDontSee($productB->name);

        $this->get('/categories')
            ->assertOk()
            ->assertSee($categoryA->name)
            ->assertDontSee($categoryB->name);

        $this->get('/products?category=' . $categoryA->slug)
            ->assertOk()
            ->assertSee($productA->name)
            ->assertDontSee($productB->name);

        $this->get('/products/' . $productB->slug)
            ->assertNotFound();
    });
});
