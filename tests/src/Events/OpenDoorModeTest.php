<?php

declare(strict_types=1);

use AIArmada\Events\Enums\OpenDoorMode;

it('has three cases', function (): void {
    expect(OpenDoorMode::cases())->toHaveCount(3);
});

it('provides labels', function (): void {
    expect(OpenDoorMode::Block->label())->toBe('Block Registration');
    expect(OpenDoorMode::WalkIn->label())->toBe('Admin Walk-in Recording');
    expect(OpenDoorMode::Headcount->label())->toBe('Headcount Logging');
});
