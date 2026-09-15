<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\BatchStatus;

/* Values/count covered by Unit/BatchStatusTest. */

it('can create batch status from value', function (): void {
    $status = BatchStatus::from('quarantined');

    expect($status)->toBe(BatchStatus::Quarantined);
});

it('throws for invalid batch status value', function (): void {
    BatchStatus::from('invalid_status');
})->throws(ValueError::class);

it('can try from value', function (): void {
    $status = BatchStatus::tryFrom('expired');
    $invalid = BatchStatus::tryFrom('invalid');

    expect($status)->toBe(BatchStatus::Expired);
    expect($invalid)->toBeNull();
});

/* movableStatuses covered by Unit/BatchStatusTest (with count). */
