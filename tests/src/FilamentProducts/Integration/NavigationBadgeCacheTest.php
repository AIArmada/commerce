<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Resources\CategoryResource;
use AIArmada\FilamentProducts\Resources\CollectionResource;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\Products\Enums\CatalogStatus;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', false);
    config()->set('products.features.owner.auto_assign_on_create', true);

    if (! Schema::hasColumn('product_collections', 'status')) {
        Schema::table('product_collections', function (Blueprint $table): void {
            $table->string('status')->default('active');
        });
    }

    if (! Schema::hasColumn('product_collections', 'visibility')) {
        Schema::table('product_collections', function (Blueprint $table): void {
            $table->string('visibility')->default('catalog');
        });
    }
});

it('caches the product navigation badge per owner', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner']);

    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    OwnerContext::withOwner($owner, static function (): void {
        Product::query()->create([
            'name' => 'Badged',
            'slug' => 'badged',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);
        Product::query()->create([
            'name' => 'Drafted',
            'slug' => 'drafted',
            'price' => 1000,
            'status' => ProductStatus::Draft,
        ]);
    });

    $badge = OwnerContext::withOwner($owner, static fn () => ProductResource::getNavigationBadge());

    expect($badge)->toBe('1');

    DB::enableQueryLog();
    DB::flushQueryLog();

    $cached = OwnerContext::withOwner($owner, static fn () => ProductResource::getNavigationBadge());

    expect($cached)->toBe('1')
        ->and(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();

    OwnerCache::forget($owner, 'filament-products.nav-badge.active-products');
});

it('caches the category navigation badge', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner']);

    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    OwnerContext::withOwner($owner, static function (): void {
        Category::query()->create(['name' => 'Cat A', 'slug' => 'cat-a']);
        Category::query()->create(['name' => 'Cat B', 'slug' => 'cat-b']);
    });

    $badge = OwnerContext::withOwner($owner, static fn () => CategoryResource::getNavigationBadge());

    expect($badge)->toBe('2');

    DB::enableQueryLog();
    DB::flushQueryLog();

    $cached = OwnerContext::withOwner($owner, static fn () => CategoryResource::getNavigationBadge());

    expect($cached)->toBe('2')
        ->and(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();

    OwnerCache::forget($owner, 'filament-products.nav-badge.categories');
});

it('caches the visible collection navigation badge', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner']);

    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    OwnerContext::withOwner($owner, static function (): void {
        Collection::query()->create([
            'name' => 'Shown',
            'slug' => 'shown',
            'type' => 'manual',
            'status' => CatalogStatus::Active,
        ]);
        Collection::query()->create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'type' => 'manual',
            'status' => CatalogStatus::Hidden,
        ]);
    });

    $badge = OwnerContext::withOwner($owner, static fn () => CollectionResource::getNavigationBadge());

    expect($badge)->toBe('1');

    DB::enableQueryLog();
    DB::flushQueryLog();

    $cached = OwnerContext::withOwner($owner, static fn () => CollectionResource::getNavigationBadge());

    expect($cached)->toBe('1')
        ->and(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();

    OwnerCache::forget($owner, 'filament-products.nav-badge.collections');
});

it('returns null badges when the owner has no records', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner']);

    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $badges = OwnerContext::withOwner($owner, static fn (): array => [
        ProductResource::getNavigationBadge(),
        CategoryResource::getNavigationBadge(),
        CollectionResource::getNavigationBadge(),
    ]);

    expect($badges)->toBe([null, null, null]);
});

it('isolates cached badges between owners', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    OwnerContext::withOwner($ownerA, static function (): void {
        Product::query()->create([
            'name' => 'Counted A',
            'slug' => 'counted-a',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);
    });

    OwnerContext::withOwner($ownerB, static function (): void {
        Product::query()->create([
            'name' => 'Counted B1',
            'slug' => 'counted-b1',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);
        Product::query()->create([
            'name' => 'Counted B2',
            'slug' => 'counted-b2',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);
    });

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $badgeA = OwnerContext::withOwner($ownerA, static fn () => ProductResource::getNavigationBadge());

    app()->instance(OwnerResolverInterface::class, new class($ownerB) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $badgeB = OwnerContext::withOwner($ownerB, static fn () => ProductResource::getNavigationBadge());

    expect($badgeA)->toBe('1')
        ->and($badgeB)->toBe('2');

    OwnerCache::forget($ownerA, 'filament-products.nav-badge.active-products');
    OwnerCache::forget($ownerB, 'filament-products.nav-badge.active-products');
});
