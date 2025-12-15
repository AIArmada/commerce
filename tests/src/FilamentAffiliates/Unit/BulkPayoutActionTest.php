<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Actions\BulkPayoutAction;

it('has correct default name', function (): void {
    expect(BulkPayoutAction::getDefaultName())->toBe('bulk_process_payouts');
});

it('can be instantiated with make method', function (): void {
    $action = BulkPayoutAction::make('bulk_process_payouts');

    expect($action)->toBeInstanceOf(BulkPayoutAction::class);
});
