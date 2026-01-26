<?php

declare(strict_types=1);

use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\States\Available;
use AIArmada\Inventory\States\Disposed;
use AIArmada\Inventory\States\InRepair;
use AIArmada\Inventory\States\Lost;
use AIArmada\Inventory\States\Reserved;
use AIArmada\Inventory\States\Returned;
use AIArmada\Inventory\States\SerialStatus;
use AIArmada\Inventory\States\Shipped;
use AIArmada\Inventory\States\Sold;

test('SerialStatus states are registered', function (): void {
    $states = SerialStatus::classes();

    expect($states)->toHaveCount(9);
    expect($states)->toContain(Available::class);
    expect($states)->toContain(Reserved::class);
    expect($states)->toContain(Sold::class);
    expect($states)->toContain(Disposed::class);
});

test('SerialStatus options returns correct array', function (): void {
    $options = SerialStatus::options(new InventorySerial);
    expect($options)->toBeArray();
    expect($options)->toHaveKey('available');
    expect($options['available'])->toBe('Available');
});

test('SerialStatus label returns correct labels', function (): void {
    $model = new InventorySerial;

    expect((new Available($model))->label())->toBe('Available');
    expect((new Reserved($model))->label())->toBe('Reserved');
    expect((new Sold($model))->label())->toBe('Sold');
    expect((new Disposed($model))->label())->toBe('Disposed');
    expect((new InRepair($model))->label())->toBe('In Repair');
});

test('SerialStatus color returns correct colors', function (): void {
    $model = new InventorySerial;

    expect((new Available($model))->color())->toBe('success');
    expect((new Reserved($model))->color())->toBe('warning');
    expect((new Sold($model))->color())->toBe('info');
    expect((new Disposed($model))->color())->toBe('danger');
});

test('SerialStatus isAllocatable works correctly', function (): void {
    $model = new InventorySerial;

    expect((new Available($model))->isAllocatable())->toBeTrue();
    expect((new Reserved($model))->isAllocatable())->toBeFalse();
    expect((new Sold($model))->isAllocatable())->toBeFalse();
});

test('SerialStatus isInStock works correctly', function (): void {
    $model = new InventorySerial;

    expect((new Available($model))->isInStock())->toBeTrue();
    expect((new Reserved($model))->isInStock())->toBeTrue();
    expect((new Sold($model))->isInStock())->toBeFalse();
    expect((new Disposed($model))->isInStock())->toBeFalse();
});

test('SerialStatus canTransitionTo works correctly', function (): void {
    $available = new InventorySerial(['status' => Available::class]);
    $sold = new InventorySerial(['status' => Sold::class]);
    $disposed = new InventorySerial(['status' => Disposed::class]);
    $lost = new InventorySerial(['status' => Lost::class]);

    expect($available->status->canTransitionTo(Reserved::class))->toBeTrue();
    expect($available->status->canTransitionTo(Disposed::class))->toBeTrue();
    expect($sold->status->canTransitionTo(Available::class))->toBeFalse();
    expect($disposed->status->canTransitionTo(Available::class))->toBeFalse();
    expect($sold->status->canTransitionTo(Shipped::class))->toBeTrue();
    expect($sold->status->canTransitionTo(Returned::class))->toBeTrue();
    expect($lost->status->canTransitionTo(Available::class))->toBeTrue();
});
