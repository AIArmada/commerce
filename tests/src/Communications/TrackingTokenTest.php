<?php

declare(strict_types=1);

use AIArmada\Communications\Actions\CreateTrackingTokenAction;
use AIArmada\Communications\Actions\RecordTrackingInteractionAction;

test('RecordTrackingInteractionAction exists', function (): void {
    $action = app(RecordTrackingInteractionAction::class);
    expect($action)->toBeInstanceOf(RecordTrackingInteractionAction::class);
});

test('CreateTrackingTokenAction exists', function (): void {
    expect(class_exists(CreateTrackingTokenAction::class))->toBeTrue();
});
