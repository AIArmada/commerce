<?php

declare(strict_types=1);

use AIArmada\Products\Actions\CreateProduct;
use AIArmada\Products\Actions\UpdateProduct;
use AIArmada\Products\Events\ProductCreated;
use AIArmada\Products\Events\ProductUpdated;
use Illuminate\Support\Facades\Event;

it('dispatches the created event exactly once through the create action', function (): void {
    Event::fake([ProductCreated::class]);

    app(CreateProduct::class)->execute(['name' => 'Single Dispatch', 'price' => 1000]);

    Event::assertDispatchedTimes(ProductCreated::class, 1);
});

it('dispatches the updated event exactly once through the update action', function (): void {
    $product = app(CreateProduct::class)->execute(['name' => 'Update Dispatch', 'price' => 1000]);

    Event::fake([ProductUpdated::class]);

    $result = app(UpdateProduct::class)->execute($product, ['name' => 'Update Dispatch v2']);

    Event::assertDispatchedTimes(ProductUpdated::class, 1);

    expect($result->name)->toBe('Update Dispatch v2');
});
