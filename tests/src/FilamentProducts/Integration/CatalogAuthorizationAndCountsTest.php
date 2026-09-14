<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource;
use AIArmada\FilamentProducts\Resources\AttributeResource;
use AIArmada\FilamentProducts\Resources\AttributeSetResource;
use AIArmada\FilamentProducts\Resources\CategoryResource;
use AIArmada\FilamentProducts\Resources\CollectionResource;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\FilamentProducts\Widgets\TopSellingProductsWidget;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

it('denies catalog resource access without granted abilities', function (): void {
    $product = new Product;

    foreach ([
        CategoryResource::class,
        CollectionResource::class,
        AttributeResource::class,
        AttributeGroupResource::class,
        AttributeSetResource::class,
    ] as $resource) {
        expect($resource::canViewAny())->toBeFalse()
            ->and($resource::canView($product))->toBeFalse()
            ->and($resource::canCreate())->toBeFalse()
            ->and($resource::canEdit($product))->toBeFalse()
            ->and($resource::canDelete($product))->toBeFalse()
            ->and($resource::shouldRegisterNavigation())->toBeFalse();
    }
});

it('grants catalog resource access through abilities', function (): void {
    Gate::define('category.viewAny', static fn (): bool => true);

    $user = User::factory()->create();
    $this->be($user);

    expect(CategoryResource::canViewAny())->toBeTrue()
        ->and(CategoryResource::shouldRegisterNavigation())->toBeTrue()
        ->and(CollectionResource::canViewAny())->toBeFalse();
});

it('eager loads product counts instead of querying per row', function (): void {
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
        $product = Product::query()->create([
            'name' => 'Counted',
            'slug' => 'counted',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);

        Variant::query()->create([
            'product_id' => $product->id,
            'sku' => 'COUNTED-1',
        ]);
    });

    $records = OwnerContext::withOwner($owner, static fn () => ProductResource::getEloquentQuery()->get());

    expect($records)->toHaveCount(1)
        ->and($records[0]->getAttribute('variants_count'))->toBe(1)
        ->and($records[0]->getAttribute('prices_count'))->not->toBeNull()
        ->and($records[0]->getAttribute('active_prices_count'))->not->toBeNull();

    DB::enableQueryLog();

    foreach ($records as $record) {
        $record->getAttribute('variants_count');
        $record->getAttribute('prices_count');
        $record->getAttribute('active_prices_count');
    }

    expect(DB::getQueryLog())->toBe([]);

    DB::disableQueryLog();
});

it('eager loads variant counts for the recent products widget', function (): void {
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
        $product = Product::query()->create([
            'name' => 'Widget Counted',
            'slug' => 'widget-counted',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);

        Variant::query()->create([
            'product_id' => $product->id,
            'sku' => 'WIDGET-COUNTED-1',
        ]);
    });

    $widget = app(TopSellingProductsWidget::class);
    $method = new ReflectionMethod(TopSellingProductsWidget::class, 'getRecentProductsQuery');

    $records = OwnerContext::withOwner($owner, static fn () => $method->invoke($widget)->get());

    expect($records[0]->getAttribute('variants_count'))->toBe(1);
});
