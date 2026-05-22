<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Pages\BulkEditProducts;
use AIArmada\FilamentProducts\Pages\ImportExportProducts;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages\ListAttributeGroups;
use AIArmada\FilamentProducts\Resources\AttributeResource\Pages\ListAttributes;
use AIArmada\FilamentProducts\Resources\AttributeSetResource\Pages\ListAttributeSets;
use AIArmada\FilamentProducts\Resources\CategoryResource\Pages\ListCategories;
use AIArmada\FilamentProducts\Resources\CategoryResource\Pages\ViewCategory;
use AIArmada\FilamentProducts\Resources\CollectionResource\Pages\ListCollections;
use AIArmada\FilamentProducts\Resources\CollectionResource\Pages\ViewCollection;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages\ListProducts;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages\ViewProduct;
use AIArmada\FilamentProducts\Widgets\CategoryDistributionChart;
use AIArmada\FilamentProducts\Widgets\ProductStatsWidget;
use AIArmada\FilamentProducts\Widgets\ProductTypeDistributionWidget;
use AIArmada\FilamentProducts\Widgets\TopSellingProductsWidget;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema as SchemaFacade;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

function makeProductsTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

function setOwner(Model $owner): void
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

beforeEach(function (): void {
    SchemaFacade::dropIfExists('test_owners');

    SchemaFacade::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', true);
});

it('covers resource page header actions methods', function (): void {
    $listProducts = new ListProducts;
    $viewProduct = new ViewProduct;
    $listCategories = new ListCategories;
    $viewCategory = new ViewCategory;
    $listCollections = new ListCollections;
    $viewCollection = new ViewCollection;
    $listAttributes = new ListAttributes;
    $listAttributeGroups = new ListAttributeGroups;
    $listAttributeSets = new ListAttributeSets;

    $getActions = function (object $page): array {
        $method = new ReflectionMethod($page::class, 'getHeaderActions');

        return $method->invoke($page);
    };

    expect($getActions($listProducts))->toBeArray()->not->toBeEmpty();
    expect($getActions($viewProduct))->toBeArray()->not->toBeEmpty();
    expect($getActions($listCategories))->toBeArray()->not->toBeEmpty();
    expect($getActions($viewCategory))->toBeArray()->not->toBeEmpty();
    expect($getActions($listCollections))->toBeArray()->not->toBeEmpty();
    expect($getActions($viewCollection))->toBeArray()->not->toBeEmpty();
    expect($getActions($listAttributes))->toBeArray()->not->toBeEmpty();
    expect($getActions($listAttributeGroups))->toBeArray()->not->toBeEmpty();
    expect($getActions($listAttributeSets))->toBeArray()->not->toBeEmpty();
});

it('covers widget query logic with owner scoping', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-10 10:00:00'));

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    setOwner($ownerA);

    $productA1 = Product::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'A1',
        'slug' => 'a1',
        'status' => ProductStatus::Active,
        'type' => ProductType::Simple,
        'price' => 1000,
        'requires_shipping' => true,
    ]);

    $productA2 = Product::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'A2',
        'slug' => 'a2',
        'status' => ProductStatus::Draft,
        'type' => ProductType::Digital,
        'price' => 1000,
        'requires_shipping' => false,
    ]);

    OwnerContext::withOwner($ownerB, static function () use ($ownerB): void {
        Product::query()->create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'name' => 'B1',
            'slug' => 'b1',
            'status' => ProductStatus::Active,
            'type' => ProductType::Simple,
            'price' => 1000,
            'requires_shipping' => true,
        ]);
    });

    $catA = Category::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'CatA',
        'slug' => 'cata',
    ]);

    $catB = OwnerContext::withOwner($ownerB, static function () use ($ownerB): Category {
        return Category::query()->create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'name' => 'CatB',
            'slug' => 'catb',
        ]);
    });

    $productA1->categories()->attach($catA);
    $productA2->categories()->attach($catA);

    $statsWidget = app(ProductStatsWidget::class);
    $statsMethod = new ReflectionMethod(ProductStatsWidget::class, 'getStats');
    $stats = $statsMethod->invoke($statsWidget);

    expect($stats)->toHaveCount(4);
    expect($stats[0]->getValue())->toBe('2');

    $chartWidget = app(CategoryDistributionChart::class);
    $dataMethod = new ReflectionMethod(CategoryDistributionChart::class, 'getData');
    $data = $dataMethod->invoke($chartWidget);

    expect($data['labels'])->toBe(['CatA']);
    expect($data['datasets'][0]['data'])->toBe([2]);

    $topWidget = app(TopSellingProductsWidget::class);
    $table = $topWidget->table(makeProductsTable());

    expect($table)->toBeInstanceOf(Table::class);
    expect($catB->getKey())->not->toBeNull();
});

it('covers basic table building on standalone pages', function (): void {
    $page = app(BulkEditProducts::class);
    expect($page->table(makeProductsTable()))->toBeInstanceOf(Table::class);

    $importPage = app(ImportExportProducts::class);
    expect($importPage->getImportFormProperty())->toBeInstanceOf(Schema::class);
});

it('renders product stats in explicit global context when no owner is resolved', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-10 10:00:00'));

    app()->forgetInstance(OwnerResolverInterface::class);

    OwnerContext::withOwner(null, static function (): void {
        Product::query()->create([
            'name' => 'Global Product',
            'slug' => 'global-product',
            'status' => ProductStatus::Active,
            'type' => ProductType::Simple,
            'price' => 1000,
            'requires_shipping' => true,
        ]);

        Category::query()->create([
            'name' => 'Global Category',
            'slug' => 'global-category',
        ]);
    });

    $statsWidget = app(ProductStatsWidget::class);
    $statsMethod = new ReflectionMethod(ProductStatsWidget::class, 'getStats');
    $stats = $statsMethod->invoke($statsWidget);

    expect($stats)->toHaveCount(4)
        ->and($stats[0]->getValue())->toBe('1');
});

it('renders product type distribution in explicit global context when no owner is resolved', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-10 10:00:00'));

    app()->forgetInstance(OwnerResolverInterface::class);

    OwnerContext::withOwner(null, static function (): void {
        Product::query()->create([
            'name' => 'Global Physical Product',
            'slug' => 'global-physical-product',
            'status' => ProductStatus::Active,
            'type' => ProductType::Simple,
            'price' => 1000,
            'requires_shipping' => true,
        ]);

        Product::query()->create([
            'name' => 'Global Digital Product',
            'slug' => 'global-digital-product',
            'status' => ProductStatus::Active,
            'type' => ProductType::Digital,
            'price' => 1000,
            'requires_shipping' => false,
        ]);
    });

    $widget = app(ProductTypeDistributionWidget::class);
    $method = new ReflectionMethod(ProductTypeDistributionWidget::class, 'getStats');
    $stats = $method->invoke($widget);

    expect($stats)->toHaveCount(4)
        ->and($stats[0]->getValue())->toBe(1)
        ->and($stats[1]->getValue())->toBe(1)
        ->and($stats[2]->getValue())->toBe(0)
        ->and($stats[3]->getValue())->toBe(2);
});

it('builds top selling products query in explicit global context when no owner is resolved', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-10 10:00:00'));

    app()->forgetInstance(OwnerResolverInterface::class);

    OwnerContext::withOwner(null, static function (): void {
        Product::query()->create([
            'name' => 'Global Active Product',
            'slug' => 'global-active-product',
            'status' => ProductStatus::Active,
            'type' => ProductType::Simple,
            'price' => 1000,
            'requires_shipping' => true,
        ]);

        Product::query()->create([
            'name' => 'Global Draft Product',
            'slug' => 'global-draft-product',
            'status' => ProductStatus::Draft,
            'type' => ProductType::Simple,
            'price' => 1000,
            'requires_shipping' => true,
        ]);
    });

    $widget = app(TopSellingProductsWidget::class);
    $method = new ReflectionMethod(TopSellingProductsWidget::class, 'getRecentProductsQuery');

    /** @var Builder<Product> $query */
    $query = $method->invoke($widget);

    expect($query->count())->toBe(1)
        ->and($query->first()?->name)->toBe('Global Active Product');
});

it('counts variants for top selling widget in explicit global context when no owner is resolved', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-10 10:00:00'));

    app()->forgetInstance(OwnerResolverInterface::class);

    $product = OwnerContext::withOwner(null, static function (): Product {
        return Product::query()->create([
            'name' => 'Global Variant Product',
            'slug' => 'global-variant-product',
            'status' => ProductStatus::Active,
            'type' => ProductType::Simple,
            'price' => 1000,
            'requires_shipping' => true,
        ]);
    });

    OwnerContext::withOwner(null, static function () use ($product): void {
        Variant::query()->create([
            'product_id' => $product->id,
            'sku' => 'GVP-001',
        ]);
    });

    $widget = app(TopSellingProductsWidget::class);
    $method = new ReflectionMethod(TopSellingProductsWidget::class, 'getVariantsCount');

    expect($method->invoke($widget, $product))->toBe(1);
});

it('renders category distribution chart in explicit global context when no owner is resolved', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-10 10:00:00'));

    app()->forgetInstance(OwnerResolverInterface::class);

    OwnerContext::withOwner(null, static function (): void {
        $product = Product::query()->create([
            'name' => 'Global Chart Product',
            'slug' => 'global-chart-product',
            'status' => ProductStatus::Active,
            'type' => ProductType::Simple,
            'price' => 1000,
            'requires_shipping' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Global Chart Category',
            'slug' => 'global-chart-category',
        ]);

        $product->categories()->attach($category);
    });

    $widget = app(CategoryDistributionChart::class);
    $method = new ReflectionMethod(CategoryDistributionChart::class, 'getData');

    /** @var array{datasets: array<int, array{data: array<int|string>}>, labels: array<string>} $data */
    $data = $method->invoke($widget);

    expect($data['labels'])->toBe(['Global Chart Category'])
        ->and($data['datasets'])->toHaveCount(1)
        ->and($data['datasets'][0]['data'])->toHaveCount(1)
        ->and((int) $data['datasets'][0]['data'][0])->toBe(1);
});
