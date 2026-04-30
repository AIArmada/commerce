<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages\CreateAttributeGroup;
use AIArmada\FilamentProducts\Resources\AttributeResource\Pages\CreateAttribute;
use AIArmada\FilamentProducts\Resources\AttributeSetResource\Pages\CreateAttributeSet;
use AIArmada\FilamentProducts\Resources\CategoryResource\Pages\CreateCategory;
use AIArmada\FilamentProducts\Resources\CategoryResource\Pages\EditCategory;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages\CreateProduct;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages\EditProduct;
use AIArmada\Products\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('passes through form data in CreateProduct mutator', function (): void {
    $page = new CreateProduct;

    $method = new ReflectionMethod(CreateProduct::class, 'mutateFormDataBeforeCreate');

    $data = $method->invoke($page, [
        'name' => 'Test Product',
        'price' => 1234,
    ]);

    expect($data['name'])->toBe('Test Product');
    expect($data['price'])->toBe(1234);
});

it('passes through form data in EditProduct mutator', function (): void {
    $page = new EditProduct;

    $saveMethod = new ReflectionMethod(EditProduct::class, 'mutateFormDataBeforeSave');

    $headerMethod = new ReflectionMethod(EditProduct::class, 'getHeaderActions');

    expect($saveMethod->invoke($page, ['price' => 1000]))->toBe(['price' => 1000]);
    expect($headerMethod->invoke($page))->toBeArray()->not->toBeEmpty();
});

it('uses parent id from request query in CreateCategory', function (): void {
    $parent = Category::query()->create(['name' => 'Parent Category']);

    request()->query->set('parent', $parent->id);

    $page = new CreateCategory;

    $method = new ReflectionMethod(CreateCategory::class, 'mutateFormDataBeforeCreate');

    $data = $method->invoke($page, []);

    expect($data['parent_id'])->toBe($parent->id);
});

it('removes cross-owner parent ids in EditCategory mutator', function (): void {
    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', false);
    config()->set('products.features.owner.auto_assign_on_create', true);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $parentA = OwnerContext::withOwner($ownerA, static function (): Category {
        return Category::query()->create(['name' => 'Parent A']);
    });

    $parentB = OwnerContext::withOwner($ownerB, static function (): Category {
        return Category::query()->create(['name' => 'Parent B']);
    });

    $page = new EditCategory;

    $method = new ReflectionMethod(EditCategory::class, 'mutateFormDataBeforeSave');

    $validData = $method->invoke($page, ['parent_id' => $parentA->id]);
    $invalidData = $method->invoke($page, ['parent_id' => $parentB->id]);

    expect($validData['parent_id'])->toBe($parentA->id)
        ->and($invalidData)->not->toHaveKey('parent_id');
});

it('executes redirect url methods (they may throw without a panel)', function (): void {
    foreach ([
        CreateAttribute::class,
        CreateAttributeGroup::class,
        CreateAttributeSet::class,
    ] as $class) {
        $instance = new $class;

        $method = new ReflectionMethod($class, 'getRedirectUrl');

        expect(fn () => $method->invoke($instance))->toThrow(Exception::class);
    }
});
