<?php

declare(strict_types=1);

use AIArmada\Inventory\States\BackorderStatus;
use AIArmada\Inventory\States\Cancelled;
use AIArmada\Inventory\States\Expired;
use AIArmada\Inventory\States\Fulfilled;
use AIArmada\Inventory\States\PartiallyFulfilled;
use AIArmada\Inventory\States\Pending;

test('BackorderStatus labels are correct', function (): void {
    expect(BackorderStatus::fromString(Pending::class)->label())->toBe('Pending');
    expect(BackorderStatus::fromString(PartiallyFulfilled::class)->label())->toBe('Partially Fulfilled');
    expect(BackorderStatus::fromString(Fulfilled::class)->label())->toBe('Fulfilled');
    expect(BackorderStatus::fromString(Cancelled::class)->label())->toBe('Cancelled');
    expect(BackorderStatus::fromString(Expired::class)->label())->toBe('Expired');
});

test('BackorderStatus colors are correct', function (): void {
    expect(BackorderStatus::fromString(Pending::class)->color())->toBe('warning');
    expect(BackorderStatus::fromString(PartiallyFulfilled::class)->color())->toBe('info');
    expect(BackorderStatus::fromString(Fulfilled::class)->color())->toBe('success');
    expect(BackorderStatus::fromString(Cancelled::class)->color())->toBe('danger');
    expect(BackorderStatus::fromString(Expired::class)->color())->toBe('gray');
});

test('BackorderStatus open/closed flags work correctly', function (): void {
    expect(BackorderStatus::fromString(Pending::class)->isOpen())->toBeTrue();
    expect(BackorderStatus::fromString(PartiallyFulfilled::class)->isOpen())->toBeTrue();
    expect(BackorderStatus::fromString(Fulfilled::class)->isOpen())->toBeFalse();
    expect(BackorderStatus::fromString(Cancelled::class)->isOpen())->toBeFalse();
    expect(BackorderStatus::fromString(Expired::class)->isOpen())->toBeFalse();

    expect(BackorderStatus::fromString(Pending::class)->isClosed())->toBeFalse();
    expect(BackorderStatus::fromString(PartiallyFulfilled::class)->isClosed())->toBeFalse();
    expect(BackorderStatus::fromString(Fulfilled::class)->isClosed())->toBeTrue();
    expect(BackorderStatus::fromString(Cancelled::class)->isClosed())->toBeTrue();
    expect(BackorderStatus::fromString(Expired::class)->isClosed())->toBeTrue();
});

test('BackorderStatus fulfill/cancel flags work correctly', function (): void {
    expect(BackorderStatus::fromString(Pending::class)->canFulfill())->toBeTrue();
    expect(BackorderStatus::fromString(PartiallyFulfilled::class)->canFulfill())->toBeTrue();
    expect(BackorderStatus::fromString(Fulfilled::class)->canFulfill())->toBeFalse();
    expect(BackorderStatus::fromString(Cancelled::class)->canFulfill())->toBeFalse();
    expect(BackorderStatus::fromString(Expired::class)->canFulfill())->toBeFalse();

    expect(BackorderStatus::fromString(Pending::class)->canCancel())->toBeTrue();
    expect(BackorderStatus::fromString(PartiallyFulfilled::class)->canCancel())->toBeTrue();
    expect(BackorderStatus::fromString(Fulfilled::class)->canCancel())->toBeFalse();
    expect(BackorderStatus::fromString(Cancelled::class)->canCancel())->toBeFalse();
    expect(BackorderStatus::fromString(Expired::class)->canCancel())->toBeFalse();
});
