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

it('passes through form data in CreateProduct mutator', function (): void {
    $page = new CreateProduct;

    $method = new ReflectionMethod(CreateProduct::class, 'mutateFormDataBeforeCreate');
    $method->setAccessible(true);

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
    $saveMethod->setAccessible(true);

    $headerMethod = new ReflectionMethod(EditProduct::class, 'getHeaderActions');
    $headerMethod->setAccessible(true);

    expect($saveMethod->invoke($page, ['price' => 1000]))->toBe(['price' => 1000]);
    expect($headerMethod->invoke($page))->toBeArray()->not->toBeEmpty();
});

it('uses parent id from request query in CreateCategory', function (): void {
    $parent = Category::query()->create(['name' => 'Parent Category']);

    request()->query->set('parent', $parent->id);

    $page = new CreateCategory;

    $method = new ReflectionMethod(CreateCategory::class, 'mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    $data = $method->invoke($page, []);

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
