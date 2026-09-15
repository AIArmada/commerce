<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\BackorderPriority;

/* Values/count covered by Unit/BackorderPriorityTest. */

it('can create backorder priority from value', function (): void {
    $priority = BackorderPriority::from('urgent');

    expect($priority)->toBe(BackorderPriority::Urgent);
});

it('throws for invalid backorder priority value', function (): void {
    BackorderPriority::from('invalid_priority');
})->throws(ValueError::class);

it('can try from value', function (): void {
    $priority = BackorderPriority::tryFrom('high');
    $invalid = BackorderPriority::tryFrom('invalid');

    expect($priority)->toBe(BackorderPriority::High);
    expect($invalid)->toBeNull();
});
