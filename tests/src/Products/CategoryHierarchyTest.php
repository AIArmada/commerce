<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\DB;

it('rejects a missing parent category', function (): void {
    expect(fn () => Category::create([
        'name' => 'Orphan', 'parent_id' => (string) Str::uuid(),
    ]))->toThrow(InvalidArgumentException::class, 'Invalid parent_id');
});

it('rejects self-parenting', function (): void {
    $category = Category::create(['name' => 'Self Parent']);

    expect(fn () => $category->update(['parent_id' => $category->id]))
        ->toThrow(InvalidArgumentException::class, 'own parent');
});

it('rejects moving a category below its own descendant', function (): void {
    $root = Category::create(['name' => 'Cycle Root']);
    $child = Category::create(['name' => 'Cycle Child', 'parent_id' => $root->id]);
    $grandchild = Category::create(['name' => 'Cycle Grandchild', 'parent_id' => $child->id]);

    expect(fn () => $root->update(['parent_id' => $grandchild->id]))
        ->toThrow(InvalidArgumentException::class, 'own descendant');
});

it('rejects a parent owned by a different owner', function (): void {
    config()->set('products.features.owner.include_global', false);

    $ownerA = User::query()->create(['name' => 'A', 'email' => 'cat-owner-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'B', 'email' => 'cat-owner-b@example.com', 'password' => 'secret']);

    $parentB = OwnerContext::withOwner($ownerB, fn (): Category => Category::create(['name' => 'B Parent']));

    expect(fn () => OwnerContext::withOwner($ownerA, fn (): Category => Category::create([
        'name' => 'A Child', 'parent_id' => $parentB->id,
    ])))->toThrow(InvalidArgumentException::class, 'same owner');
});

it('terminates ancestor and tree traversal on a legacy cycle', function (): void {
    $first = Category::create(['name' => 'Legacy A']);
    $second = Category::create(['name' => 'Legacy B', 'parent_id' => $first->id]);

    // Plant a cycle beneath the model events, as legacy data would present it.
    Category::withoutEvents(function () use ($first, $second): void {
        DB::table((new Category)->getTable())->where('id', $first->id)->update(['parent_id' => $second->id]);
    });

    $ancestors = $first->fresh()->getAncestors();
    $tree = $first->fresh()->getNestedTree();

    expect($ancestors->count())->toBeLessThanOrEqual(2)
        ->and($tree['children'])->not->toBeEmpty();
});

it('counts subtree products with a bounded number of queries', function (): void {
    $parent = Category::create(['name' => 'Wide Parent']);

    for ($i = 0; $i < 20; $i++) {
        $child = Category::create(['name' => "Wide Child {$i}", 'parent_id' => $parent->id]);
        $product = Product::create(['name' => "Wide Product {$i}", 'price' => 1000, 'status' => ProductStatus::Active]);
        $child->products()->attach($product->id);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    $count = $parent->getProductCount(true);
    $queryCount = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($count)->toBe(20)
        ->and($queryCount)->toBeLessThanOrEqual(10);
});

it('counts each distinct subtree product once', function (): void {
    $parent = Category::create(['name' => 'Overlap Parent']);
    $child = Category::create(['name' => 'Overlap Child', 'parent_id' => $parent->id]);

    $product = Product::create(['name' => 'Overlap Product', 'price' => 1000, 'status' => ProductStatus::Active]);
    $parent->products()->attach($product->id);
    $child->products()->attach($product->id);

    expect($parent->getProductCount(true))->toBe(1)
        ->and($parent->getAllProducts())->toHaveCount(1);
});

it('scopes category products to the category owner', function (): void {
    config()->set('products.features.owner.include_global', false);

    $ownerA = User::query()->create(['name' => 'A', 'email' => 'cat-scope-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'B', 'email' => 'cat-scope-b@example.com', 'password' => 'secret']);

    $category = OwnerContext::withOwner($ownerA, fn (): Category => Category::create(['name' => 'Scoped Category']));
    $productA = OwnerContext::withOwner($ownerA, fn (): Product => Product::create(['name' => 'Scoped A', 'price' => 1000]));
    $productB = OwnerContext::withOwner($ownerB, fn (): Product => Product::create(['name' => 'Scoped B', 'price' => 1000]));

    $category->products()->attach([$productA->id, $productB->id]);

    expect($category->products()->pluck('id')->all())->toBe([$productA->id]);
});
