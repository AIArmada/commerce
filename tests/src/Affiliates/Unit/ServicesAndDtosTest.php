<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Paused;

// Affiliate status enum edge cases
test('AffiliateStatus disabled status works correctly', function (): void {
    $affiliate = new Affiliate(['status' => Disabled::class]);

    expect($affiliate->isActive())->toBeFalse();
    expect($affiliate->status->equals(Disabled::class))->toBeTrue();
});

test('AffiliateStatus paused status works correctly', function (): void {
    $affiliate = new Affiliate(['status' => Paused::class]);

    expect($affiliate->isActive())->toBeFalse();
    expect($affiliate->status->equals(Paused::class))->toBeTrue();
});

// Program status edge cases
test('ProgramStatus paused works correctly', function (): void {
    $program = new AffiliateProgram(['status' => ProgramStatus::Paused]);

    expect($program->isActive())->toBeFalse();
});

test('ProgramStatus archived works correctly', function (): void {
    $program = new AffiliateProgram(['status' => ProgramStatus::Archived]);

    expect($program->isActive())->toBeFalse();
});
