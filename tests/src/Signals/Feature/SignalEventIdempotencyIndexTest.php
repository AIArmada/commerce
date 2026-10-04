<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Models\SignalEvent;
use Illuminate\Support\Facades\Schema;

uses(SignalsTestCase::class);

it('uses a short explicit unique index for the idempotency scope', function (): void {
    $table = (new SignalEvent)->getTable();
    $indexName = 'signal_events_idem_unique';

    expect(mb_strlen($indexName))->toBeLessThanOrEqual(64)
        ->and(Schema::hasIndex($table, $indexName))->toBeTrue();

    $generated = $table . '_tracked_property_id_ingestion_source_idempotency_key_unique';

    expect(mb_strlen($generated))->toBeGreaterThan(64);

    $indexes = Schema::getIndexes($table);
    $match = collect($indexes)->firstWhere('name', $indexName);

    expect($match)->not->toBeNull()
        ->and($match['columns'] ?? [])->toEqualCanonicalizing(['tracked_property_id', 'ingestion_source', 'idempotency_key'])
        ->and($match['unique'] ?? false)->toBeTrue();
});
