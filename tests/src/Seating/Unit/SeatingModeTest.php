<?php

declare(strict_types=1);

use AIArmada\Seating\Enums\SeatingMode;

it('has four cases', function (): void {
    expect(SeatingMode::cases())->toHaveCount(4);
});

it('requires allocation for non-None modes', function (): void {
    expect(SeatingMode::None->requiresAllocation())->toBeFalse();
    expect(SeatingMode::GeneralAdmission->requiresAllocation())->toBeTrue();
    expect(SeatingMode::Assigned->requiresAllocation())->toBeTrue();
    expect(SeatingMode::Hybrid->requiresAllocation())->toBeTrue();
});

it('returns correct labels', function (): void {
    expect(SeatingMode::None->label())->toBe('None');
    expect(SeatingMode::GeneralAdmission->label())->toBe('General Admission');
    expect(SeatingMode::Assigned->label())->toBe('Assigned');
    expect(SeatingMode::Hybrid->label())->toBe('Hybrid');
});

it('returns null for invalid string', function (): void {
    expect(SeatingMode::tryFrom('invalid'))->toBeNull();
    expect(SeatingMode::tryFrom('reserved'))->toBeNull();
});

it('parses valid strings', function (): void {
    expect(SeatingMode::tryFrom('none'))->toBe(SeatingMode::None);
    expect(SeatingMode::tryFrom('general_admission'))->toBe(SeatingMode::GeneralAdmission);
    expect(SeatingMode::tryFrom('assigned'))->toBe(SeatingMode::Assigned);
    expect(SeatingMode::tryFrom('hybrid'))->toBe(SeatingMode::Hybrid);
});
