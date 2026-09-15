<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\AlertStatus;

/* Values/count covered by Unit/AlertStatusTest. */

it('can create alert status from value', function (): void {
    $status = AlertStatus::from('low_stock');

    expect($status)->toBe(AlertStatus::LowStock);
});

it('throws for invalid alert status value', function (): void {
    AlertStatus::from('invalid_status');
})->throws(ValueError::class);

it('can try from value', function (): void {
    $status = AlertStatus::tryFrom('low_stock');
    $invalid = AlertStatus::tryFrom('invalid');

    expect($status)->toBe(AlertStatus::LowStock);
    expect($invalid)->toBeNull();
});

/* criticalStatuses covered by Unit/AlertStatusTest (with count). */
