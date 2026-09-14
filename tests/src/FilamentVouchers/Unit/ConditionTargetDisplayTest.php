<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Support\ConditionTargetDisplay;
use AIArmada\FilamentVouchers\Support\ConditionTargetPreset;

uses(TestCase::class);

it('falls back to the default target for unparseable definitions', function (): void {
    $dsl = ConditionTargetDisplay::dsl(['kind' => 'nope', 'value' => '???']);

    expect($dsl)->toBeString()->not->toBe('')
        ->and(ConditionTargetDisplay::definition(['kind' => 'nope']))->toBeArray()->not->toBe([])
        ->and(ConditionTargetDisplay::presetLabel(null))->toBeString()
        ->and(ConditionTargetDisplay::definition('not-an-array'))->toBeArray();
});

it('renders valid definitions unchanged', function (): void {
    $definition = ConditionTargetPreset::default()->target()?->toArray();

    expect($definition)->toBeArray()->not->toBe([])
        ->and(ConditionTargetDisplay::definition($definition))->toBe($definition)
        ->and(ConditionTargetDisplay::dsl($definition))->toBe(ConditionTargetPreset::default()->dsl());
});
