<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use Illuminate\Support\Facades\Schema;

uses(SignalsTestCase::class);

it('creates encrypted alert destinations as text columns', function (): void {
    $rules = config('signals.database.tables.alert_rules', 'signal_alert_rules');
    $deliveries = config('signals.database.tables.alert_deliveries', 'signal_alert_deliveries');

    expect(Schema::getColumnType($rules, 'inline_destinations'))->toBe('text')
        ->and(Schema::getColumnType($deliveries, 'destination'))->toBe('text');
});
