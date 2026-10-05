<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsEngineTestCase;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalAlertDelivery;
use AIArmada\Signals\Models\SignalAlertLog;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalDailyMetric;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalInteractionRule;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(SignalsEngineTestCase::class);

function engineSignalsTables(): array
{
    return [
        (new TrackedProperty)->getTable(),
        (new SignalIdentity)->getTable(),
        (new SignalSession)->getTable(),
        (new SignalEvent)->getTable(),
        (new SignalDailyMetric)->getTable(),
        (new SignalSegment)->getTable(),
        (new SavedSignalReport)->getTable(),
        (new SignalAlertRule)->getTable(),
        (new SignalAlertLog)->getTable(),
        (new SignalGoal)->getTable(),
        (new SignalInteractionRule)->getTable(),
        (new SignalAlertDelivery)->getTable(),
    ];
}

it('creates the current signals schema on the engine', function (string $engine): void {
    $this->useEngine($engine);

    $info = $this->engineVersionAndIsolation();

    expect($info['version'])->not->toBeEmpty()
        ->and($info['isolation'])->not->toBeEmpty();

    if ($engine === 'pgsql') {
        $timeZone = (string) (DB::selectOne('show timezone')->TimeZone ?? '');

        expect($timeZone)->toBe('UTC');
    } else {
        $timeZone = (string) (DB::selectOne('SELECT @@session.time_zone AS tz')->tz ?? '');

        expect($timeZone)->toBe('+00:00');
    }

    foreach (engineSignalsTables() as $table) {
        expect(Schema::hasTable($table))->toBeTrue('missing table ' . $table);
    }

    expect(Schema::hasTable('users'))->toBeTrue();
})->with(['pgsql', 'mysql']);

it('keeps every signals index name within the engine identifier bound', function (string $engine): void {
    $this->useEngine($engine);

    $bound = $engine === 'pgsql' ? 63 : 64;

    $table = (new SignalEvent)->getTable();
    $indexName = 'signal_events_idem_unique';

    expect(mb_strlen($indexName))->toBeLessThanOrEqual($bound)
        ->and(Schema::hasIndex($table, $indexName))->toBeTrue();

    $match = collect(Schema::getIndexes($table))->firstWhere('name', $indexName);

    expect($match)->not->toBeNull()
        ->and($match['columns'] ?? [])->toEqualCanonicalizing(['tracked_property_id', 'ingestion_source', 'idempotency_key'])
        ->and($match['unique'] ?? false)->toBeTrue();

    $identityIndexes = collect(Schema::getIndexes((new SignalIdentity)->getTable()));
    $sessionIndexes = collect(Schema::getIndexes((new SignalSession)->getTable()));

    expect($identityIndexes->first(static fn (array $index): bool => ($index['unique'] ?? false)
        && collect($index['columns'] ?? [])->sort()->values()->all() === ['external_id', 'tracked_property_id']))->not->toBeNull()
        ->and($sessionIndexes->first(static fn (array $index): bool => ($index['unique'] ?? false)
        && collect($index['columns'] ?? [])->sort()->values()->all() === ['session_identifier', 'tracked_property_id']))->not->toBeNull();

    $trackedMatch = collect(Schema::getIndexes((new TrackedProperty)->getTable()))
        ->firstWhere('name', 'sig_tracked_props_scope_type_idx');

    expect($trackedMatch)->not->toBeNull()
        ->and($trackedMatch['columns'] ?? [])->toEqualCanonicalizing(['owner_scope', 'type', 'is_active', 'created_at']);

    $interactionMatch = collect(Schema::getIndexes((new SignalInteractionRule)->getTable()))
        ->firstWhere('name', 'sig_interact_rules_scope_sort_idx');

    expect($interactionMatch)->not->toBeNull()
        ->and($interactionMatch['columns'] ?? [])->toEqualCanonicalizing(['owner_scope', 'tracked_property_id', 'is_active', 'sort_order', 'created_at']);

    foreach (engineSignalsTables() as $signalsTable) {
        foreach (Schema::getIndexes($signalsTable) as $index) {
            expect(mb_strlen((string) $index['name']))->toBeLessThanOrEqual($bound, 'index too long: ' . $index['name']);
        }
    }
})->with(['pgsql', 'mysql']);
