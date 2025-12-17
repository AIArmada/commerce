<?php

declare(strict_types=1);

use AIArmada\FilamentDocs\Actions\RecordPaymentAction;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

test('record payment action is configured correctly', function (): void {
    $action = RecordPaymentAction::make();

    expect($action)->toBeInstanceOf(Action::class);
    expect($action->getLabel())->toBe('Record Payment');
    expect($action->getIcon())->toBe(Heroicon::OutlinedBanknotes);
    expect($action->getColor())->toBe('success');
});

