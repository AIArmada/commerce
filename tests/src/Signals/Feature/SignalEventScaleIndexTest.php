<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(SignalsTestCase::class);

it('uses the event category and occurred at index for a large page view read', function (): void {
    /** @var User $owner */
    $owner = User::query()->firstOrFail();

    $expectedRows = 100_000;
    $chunkSize = 1_000;
    OwnerContext::withOwner($owner, function () use ($chunkSize, $expectedRows, $owner): void {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Scale Test Property',
            'slug' => 'scale-test-property',
            'write_key' => 'scale-test-property-key',
        ]);
        $tableName = (new SignalEvent)->getTable();

        /**
         * Build rows through the owned model so the fixture uses the same
         * owner assignment contract as a real SignalEvent write.
         *
         * @return array<string, mixed>
         */
        $buildSyntheticEvent = static function (int $index) use ($owner, $trackedProperty): array {
            $event = new SignalEvent;
            $event->setAttribute('id', sprintf('00000000-0000-4000-8000-%012x', $index + 1));
            $event->setAttribute('tracked_property_id', $trackedProperty->id);
            $event->setAttribute('signal_session_id', null);
            $event->setAttribute('signal_identity_id', null);
            $event->assignOwner($owner);
            $event->setAttribute('occurred_at', gmdate('Y-m-d H:i:s', 1_767_225_600 + $index));
            $event->setAttribute('event_name', $index % 2 === 0 ? 'page_view' : 'checkout.started');
            $event->setAttribute('event_category', $index % 2 === 0 ? 'page_view' : 'conversion');
            $event->setAttribute('revenue_minor', 0);
            $event->setAttribute('currency', 'MYR');
            $event->setAttribute('properties', null);
            $event->setAttribute('property_types', null);
            $event->setAttribute('created_at', '2026-01-01 00:00:00');
            $event->setAttribute('updated_at', '2026-01-01 00:00:00');

            return $event->getAttributes();
        };

        for ($offset = 0; $offset < $expectedRows; $offset += $chunkSize) {
            $rows = [];

            for ($index = $offset; $index < min($offset + $chunkSize, $expectedRows); $index++) {
                $rows[] = $buildSyntheticEvent($index);
            }

            DB::table($tableName)->insert($rows);
        }

        expect(SignalEvent::query()->count())->toBe($expectedRows);

        $from = '2026-01-01 12:00:00';
        $until = '2026-01-02 11:59:59';
        $query = SignalEvent::query()
            ->where('event_category', 'page_view')
            ->where('tracked_property_id', $trackedProperty->id)
            ->where('occurred_at', '>=', $from)
            ->where('occurred_at', '<=', $until);
        $indexName = $tableName . '_event_category_occurred_at_index';

        expect(Schema::hasIndex($tableName, $indexName))->toBeTrue();

        // SQLite reports "USING INDEX" while PostgreSQL reports the index in its plan text.
        $driver = DB::connection()->getDriverName();
        $explainSql = $driver === 'pgsql'
            ? 'EXPLAIN ' . $query->toSql()
            : 'EXPLAIN QUERY PLAN ' . $query->toSql();
        $planRows = DB::select($explainSql, $query->getBindings());
        $planText = mb_strtolower(implode("\n", array_map(
            static fn (object $row): string => implode(' ', array_map(
                static fn (mixed $value): string => (string) $value,
                get_object_vars($row),
            )),
            $planRows,
        )));

        if ($driver === 'sqlite') {
            expect($planText)->toContain('using index ' . mb_strtolower($indexName));
        } elseif ($driver === 'pgsql') {
            expect($planText)->toContain(mb_strtolower($indexName));
        } else {
            throw new RuntimeException("Unsupported database driver for EXPLAIN assertion: {$driver}");
        }

        expect($query->count())->toBe(28_400);
    });
});
