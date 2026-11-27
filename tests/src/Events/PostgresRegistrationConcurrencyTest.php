<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Registrations\Actions\RegisterForFreeAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('serializes sibling session bookings against the last occurrence place on PostgreSQL', function (): void {
    if (getenv('EVENTS_POSTGRES_CONCURRENCY') !== 'true') {
        $this->markTestSkipped('Set EVENTS_POSTGRES_CONCURRENCY=true with PostgreSQL DB_* settings.');
    }

    $originalConnection = DB::getDefaultConnection();
    $schema = 'events_test_' . bin2hex(random_bytes(8));
    $connection = [
        'driver' => 'pgsql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '5432',
        'database' => getenv('DB_DATABASE') ?: 'commerce_test',
        'username' => getenv('DB_USERNAME') ?: 'postgres',
        'password' => getenv('DB_PASSWORD') ?: '',
        'search_path' => $schema,
    ];

    config([
        'database.connections.events_postgres_first' => $connection,
        'database.connections.events_postgres_second' => $connection,
        'events.database.json_column_type' => 'jsonb',
        'events.features.owner.enabled' => true,
        'events.features.free_only.auto_derive_pricing_from_ticket_types' => false,
        'events.sync.build_search_documents' => false,
    ]);

    $first = DB::connection('events_postgres_first');
    $second = DB::connection('events_postgres_second');
    $first->statement("CREATE SCHEMA {$schema}");

    try {
        DB::setDefaultConnection('events_postgres_first');

        $exitCode = Artisan::call('migrate', [
            '--database' => 'events_postgres_first',
            '--path' => dirname(__DIR__, 3) . '/packages/events/database/migrations',
            '--realpath' => true,
            '--force' => true,
        ]);

        expect($exitCode)->toBe(0)
            ->and($first->getDriverName())->toBe('pgsql')
            ->and($first->selectOne('SELECT pg_backend_pid() AS pid')->pid)
            ->not->toBe($second->selectOne('SELECT pg_backend_pid() AS pid')->pid);

        $second->statement("SET lock_timeout = '1s'");

        OwnerContext::withOwner(null, function () use ($first): void {
            $event = Event::factory()->free()->published()->create();
            $occurrence = EventOccurrence::factory()->create([
                'event_id' => $event->id,
                'capacity' => 1,
            ]);
            [$firstSession, $secondSession] = EventSession::factory()->count(2)->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'capacity' => 1,
            ])->all();

            $register = app(RegisterForFreeAction::class);
            $bookFirst = fn (): array => $register->execute('session', $firstSession->id, [
                ['name' => 'First registrant'],
            ], options: ['defer_pass_issuance' => true]);
            $bookSecond = fn (): array => $register->execute('session', $secondSession->id, [
                ['name' => 'Second registrant'],
            ], options: ['defer_pass_issuance' => true]);

            $first->transaction(function () use ($bookFirst, $bookSecond): void {
                expect($bookFirst())->toHaveCount(1);

                DB::setDefaultConnection('events_postgres_second');

                try {
                    expect($bookSecond)->toThrow(QueryException::class, '55P03');
                } finally {
                    DB::setDefaultConnection('events_postgres_first');
                }
            });

            DB::setDefaultConnection('events_postgres_second');

            expect($bookSecond)->toThrow(EventCapacityExceededException::class)
                ->and(EventRegistration::query()->where('event_occurrence_id', $occurrence->id)->count())->toBe(1);
        });
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('events_postgres_second');
        $first->statement("DROP SCHEMA {$schema} CASCADE");
        DB::purge('events_postgres_first');
    }
});
