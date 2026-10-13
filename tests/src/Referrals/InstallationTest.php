<?php

declare(strict_types=1);

namespace Tests\src\Referrals;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;

uses(ReferralsTestCase::class);

test('package config merges and migrations run', function (): void {
    /** @var Migrator $migrator */
    $migrator = app('migrator');
    $migrationPaths = collect($migrator->paths())
        ->filter(fn (string $path): bool => str_contains($path, 'packages/referrals/')
            && str_contains($path, 'database/migrations/'))
        ->values()
        ->all();
    $migrationNames = array_keys($migrator->getMigrationFiles($migrationPaths));
    $ranMigrations = $migrator->getRepository()->getRan();
    $missingMigrations = array_diff($migrationNames, $ranMigrations);

    expect(config('referrals.database.tables.programs'))->toBe('referral_programs')
        ->and($migrationNames)->not->toBeEmpty()
        ->and($missingMigrations)->toBeEmpty();

    foreach (config('referrals.database.tables') as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});
