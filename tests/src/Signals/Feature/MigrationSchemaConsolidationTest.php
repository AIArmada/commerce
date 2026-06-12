<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use Illuminate\Support\Facades\Schema;

uses(SignalsTestCase::class);

it('creates the event property types column in the base event migration', function (): void {
    $tableName = 'custom_signal_events';

    config()->set('signals.database.tables.events', $tableName);
    config()->set('signals.database.json_column_type', 'json');

    Schema::dropIfExists($tableName);

    $migration = require __DIR__ . '/../../../../packages/signals/database/migrations/2001_01_01_000004_create_signals_events_table.php';
    $migration->up();

    expect(Schema::hasTable($tableName))->toBeTrue()
        ->and(Schema::hasColumn($tableName, 'property_types'))->toBeTrue()
        ->and(Schema::hasColumn($tableName, 'properties'))->toBeTrue();
});

it('creates the auth user columns in the base identity migration', function (): void {
    $tableName = 'custom_signal_identities';

    config()->set('signals.database.tables.identities', $tableName);
    config()->set('signals.database.json_column_type', 'json');

    Schema::dropIfExists($tableName);

    $migration = require __DIR__ . '/../../../../packages/signals/database/migrations/2001_01_01_000002_create_signals_identities_table.php';
    $migration->up();

    expect(Schema::hasTable($tableName))->toBeTrue()
        ->and(Schema::hasColumn($tableName, 'auth_user_type'))->toBeTrue()
        ->and(Schema::hasColumn($tableName, 'auth_user_id'))->toBeTrue()
        ->and(Schema::hasIndex($tableName, sprintf('%s_auth_user_type_auth_user_id_index', $tableName)))->toBeTrue();
});

it('creates the owner scope column in the base interaction rule migration', function (): void {
    $tableName = 'custom_signal_interaction_rules';

    config()->set('signals.database.tables.interaction_rules', $tableName);
    config()->set('signals.database.json_column_type', 'json');

    Schema::dropIfExists($tableName);

    $migration = require __DIR__ . '/../../../../packages/signals/database/migrations/2001_01_01_000011_create_signals_interaction_rules_table.php';
    $migration->up();

    expect(Schema::hasTable($tableName))->toBeTrue()
        ->and(Schema::hasColumn($tableName, 'owner_scope'))->toBeTrue()
        ->and(Schema::hasIndex($tableName, sprintf('%s_owner_scope_slug_unique', $tableName)))->toBeTrue();
});
