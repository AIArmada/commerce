<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Services\AttributionModel;
use AIArmada\Affiliates\Services\CommissionCalculator;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;

// CommissionCalculator Tests
test('CommissionCalculator can be instantiated', function (): void {
    $calculator = app(CommissionCalculator::class);

    expect($calculator)->toBeInstanceOf(CommissionCalculator::class);
});

// AttributionModel Tests
test('AttributionModel can be instantiated', function (): void {
    $model = app(AttributionModel::class);

    expect($model)->toBeInstanceOf(AttributionModel::class);
});

// AffiliateLinkGenerator Tests
test('AffiliateLinkGenerator can be instantiated', function (): void {
    $generator = app(AffiliateLinkGenerator::class);

    expect($generator)->toBeInstanceOf(AffiliateLinkGenerator::class);
});

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
