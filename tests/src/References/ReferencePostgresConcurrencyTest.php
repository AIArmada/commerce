<?php

declare(strict_types=1);

use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('serializes hierarchy moves without blocking ordinary child edits on PostgreSQL', function (): void {
    if (getenv('DB_CONNECTION') !== 'pgsql') {
        $this->markTestSkipped('Run with DB_CONNECTION=pgsql and PostgreSQL DB_* settings.');
    }

    $schema = 'references_test_' . bin2hex(random_bytes(8));
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
        'database.connections.references_first' => $connection,
        'database.connections.references_second' => $connection,
        'references.owner.enabled' => false,
    ]);

    $first = DB::connection('references_first');
    $second = DB::connection('references_second');
    $first->statement("CREATE SCHEMA {$schema}");

    try {
        expect(Artisan::call('migrate', [
            '--database' => 'references_first',
            '--path' => dirname(__DIR__, 3) . '/packages/references/database/migrations',
            '--realpath' => true,
            '--force' => true,
        ]))->toBe(0);

        expect($first->selectOne('SELECT pg_backend_pid() AS pid')->pid)
            ->not->toBe($second->selectOne('SELECT pg_backend_pid() AS pid')->pid);

        $origin = Reference::on('references_first')->create(['type' => ReferenceType::Book, 'title' => 'Origin Work']);
        $destination = Reference::on('references_first')->create(['type' => ReferenceType::Book, 'title' => 'Destination Work']);
        $edition = Reference::on('references_first')->create([
            'type' => ReferenceType::Book,
            'title' => 'Moving Edition',
            'record_kind' => ReferenceRecordKind::Edition,
            'parent_id' => $origin->getKey(),
        ]);
        $second->statement("SET lock_timeout = '1s'");

        $first->transaction(function () use ($origin, $destination, $edition): void {
            Reference::on('references_first')->create([
                'type' => ReferenceType::Book,
                'title' => 'New Sibling',
                'record_kind' => ReferenceRecordKind::Edition,
                'parent_id' => $origin->getKey(),
            ]);

            $editable = Reference::on('references_second')->findOrFail($edition->getKey());
            expect($editable->update(['title' => 'Edited Edition']))->toBeTrue()
                ->and(fn (): bool => $editable->update(['parent_id' => $destination->getKey()]))
                ->toThrow(QueryException::class, '55P03');
        });

        $edition = Reference::on('references_second')->findOrFail($edition->getKey());
        expect($edition->title)->toBe('Edited Edition')
            ->and($edition->update(['parent_id' => $destination->getKey()]))->toBeTrue()
            ->and($edition->fresh()->parent_id)->toBe($destination->getKey());
    } finally {
        DB::purge('references_second');
        $first->statement("DROP SCHEMA {$schema} CASCADE");
        DB::purge('references_first');
    }
});
