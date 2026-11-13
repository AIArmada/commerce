<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Support\ProductStatsAggregator;
use AIArmada\FilamentProducts\Support\ProductStatsCache;
use AIArmada\FilamentProducts\Widgets\ProductTypeDistributionWidget;
use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
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
});

afterEach(function (): void {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function bindProductStatsOwner(Model $owner): void
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

function createProductStatsProduct(Model $owner, array $attributes, ?CarbonImmutable $createdAt = null): Product
{
    return OwnerContext::withOwner($owner, static function () use ($attributes, $createdAt): Product {
        $product = Product::query()->create([
            'name' => 'Product ' . uniqid(),
            'slug' => 'product-' . uniqid(),
            'sku' => 'sku-' . uniqid(),
            'price' => 1000,
            ...$attributes,
        ]);

        if ($createdAt !== null) {
            DB::table($product->getTable())
                ->where('id', $product->getKey())
                ->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        }

        return $product;
    });
}

it('returns zeroed product statistics when the owner has no catalog data', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner']);

    bindProductStatsOwner($owner);
    OwnerCache::forget($owner, ProductStatsCache::KEY);

    $stats = OwnerContext::withOwner($owner, static fn (): array => app(ProductStatsAggregator::class)->getStats());

    expect($stats)->toMatchArray([
        'totalProducts' => 0,
        'activeProducts' => 0,
        'draftProducts' => 0,
        'totalCategories' => 0,
        'totalCollections' => 0,
        'lastWeekProducts' => 0,
        'previousWeekProducts' => 0,
        'trend' => 0,
    ]);
});

it('preserves product status and week-window boundaries in one aggregate', function (): void {
    $now = CarbonImmutable::parse('2025-01-10 10:00:00');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $lastWeekStart = $now->subWeek();
    $previousWeekStart = $now->subWeeks(2);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    bindProductStatsOwner($ownerA);
    OwnerCache::forget($ownerA, ProductStatsCache::KEY);

    createProductStatsProduct($ownerA, ['status' => ProductStatus::Active, 'type' => ProductType::Simple]);
    createProductStatsProduct($ownerA, ['status' => ProductStatus::Draft, 'type' => ProductType::Digital]);
    createProductStatsProduct($ownerA, ['status' => ProductStatus::Disabled, 'type' => ProductType::Simple]);

    createProductStatsProduct(
        $ownerA,
        ['status' => ProductStatus::Archived, 'type' => ProductType::Simple],
        $lastWeekStart,
    );
    createProductStatsProduct(
        $ownerA,
        ['status' => ProductStatus::Archived, 'type' => ProductType::Simple],
        $lastWeekStart->subSecond(),
    );
    createProductStatsProduct(
        $ownerA,
        ['status' => ProductStatus::Archived, 'type' => ProductType::Simple],
        $previousWeekStart,
    );
    createProductStatsProduct(
        $ownerA,
        ['status' => ProductStatus::Archived, 'type' => ProductType::Simple],
        $previousWeekStart->subSecond(),
    );

    createProductStatsProduct($ownerB, ['status' => ProductStatus::Active, 'type' => ProductType::Simple]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $stats = OwnerContext::withOwner($ownerA, static fn (): array => app(ProductStatsAggregator::class)->getStats());

    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    expect($stats)->toMatchArray([
        'totalProducts' => 7,
        'activeProducts' => 1,
        'draftProducts' => 1,
        'lastWeekProducts' => 4,
        'previousWeekProducts' => 3,
        'trend' => 33.0,
    ])->and(count($queries))->toBeLessThanOrEqual(3);
});

it('keeps product type distribution values while aggregating them in one query', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    bindProductStatsOwner($ownerA);

    $component = createProductStatsProduct($ownerA, [
        'status' => ProductStatus::Active,
        'type' => ProductType::Simple,
        'requires_shipping' => true,
    ]);
    createProductStatsProduct($ownerA, [
        'status' => ProductStatus::Active,
        'type' => ProductType::Configurable,
        'requires_shipping' => true,
    ]);
    OwnerContext::withOwner($ownerA, static fn (): Product => app(CreateCompositeProduct::class)->handle([
        'name' => 'Product package',
        'sku' => 'package-test',
        'price' => 1000,
        'status' => ProductStatus::Active,
    ], [['component_product_id' => (string) $component->getKey(), 'quantity' => 1]]));
    createProductStatsProduct($ownerA, [
        'status' => ProductStatus::Active,
        'type' => ProductType::Digital,
        'requires_shipping' => true,
    ]);
    createProductStatsProduct($ownerA, [
        'status' => ProductStatus::Active,
        'type' => ProductType::Subscription,
        'requires_shipping' => false,
    ]);
    createProductStatsProduct($ownerA, [
        'status' => ProductStatus::Draft,
        'type' => ProductType::Simple,
        'requires_shipping' => true,
    ]);
    createProductStatsProduct($ownerB, [
        'status' => ProductStatus::Active,
        'type' => ProductType::Simple,
        'requires_shipping' => true,
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $widget = app(ProductTypeDistributionWidget::class);
    $method = new ReflectionMethod(ProductTypeDistributionWidget::class, 'getStats');
    $stats = OwnerContext::withOwner($ownerA, static fn (): array => $method->invoke($widget));

    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    expect($stats[0]->getValue())->toBe(3)
        ->and($stats[1]->getValue())->toBe(1)
        ->and($stats[2]->getValue())->toBe(1)
        ->and($stats[3]->getValue())->toBe(5)
        ->and(count($queries))->toBeLessThanOrEqual(1);
});
