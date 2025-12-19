<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages\CreateAttributeGroup;
use AIArmada\FilamentProducts\Resources\AttributeResource\Pages\CreateAttribute;
use AIArmada\FilamentProducts\Resources\AttributeSetResource\Pages\CreateAttributeSet;
use AIArmada\FilamentProducts\Resources\CategoryResource\Pages\CreateCategory;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages\CreateProduct;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages\EditProduct;
use AIArmada\Products\Models\Category;

uses(TestCase::class);

it('converts monetary fields in CreateProduct to cents', function (): void {
    $page = new class extends CreateProduct
    {
        public function mutate(array $data): array
        {
            return $this->mutateFormDataBeforeCreate($data);
        }
    };

    $data = $page->mutate([
        'price' => 12.34,
        'compare_price' => 99.99,
        'cost' => 1.23,
    ]);

    expect($data['price'])->toBe(1234);
    expect($data['compare_price'])->toBe(9999);
    expect($data['cost'])->toBe(123);
});

it('converts monetary fields in EditProduct between cents and display values', function (): void {
    $page = new class extends EditProduct
    {
        public function mutateFill(array $data): array
        {
            return $this->mutateFormDataBeforeFill($data);
        }

        public function mutateSave(array $data): array
        {
            return $this->mutateFormDataBeforeSave($data);
        }

        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    expect($page->mutateFill(['price' => 1000]))->toBe(['price' => 10]);
    expect($page->mutateSave(['price' => 10.0]))->toBe(['price' => 1000]);
    expect($page->headerActions())->toBeArray()->not->toBeEmpty();
});

it('uses parent id from request query in CreateCategory', function (): void {
    $parent = Category::query()->create(['name' => 'Parent Category']);

    request()->query->set('parent', $parent->id);

    $page = new class extends CreateCategory
    {
        public function mutate(array $data): array
        {
            return $this->mutateFormDataBeforeCreate($data);
        }
    };

    $data = $page->mutate([]);

    expect($data['parent_id'])->toBe($parent->id);
});

it('executes redirect url methods (they may throw without a panel)', function (): void {
    foreach ([
        CreateAttribute::class,
        CreateAttributeGroup::class,
        CreateAttributeSet::class,
    ] as $class) {
        $instance = new $class;

        $method = new ReflectionMethod($class, 'getRedirectUrl');
        $method->setAccessible(true);

        expect(fn () => $method->invoke($instance))->toThrow(\Exception::class);
    }
});
