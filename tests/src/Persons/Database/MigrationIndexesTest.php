<?php

declare(strict_types=1);

use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Models\Person;
use AIArmada\Persons\Models\PersonName;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('adds the identity and assignment indexes idempotently', function (): void {
    $tables = [
        'persons' => config('persons.database.tables.persons', 'persons'),
        'person_names' => config('persons.database.tables.person_names', 'person_names'),
        'title_assignments' => config('persons.database.tables.title_assignments', 'title_assignments'),
        'credential_assignments' => config('persons.database.tables.credential_assignments', 'credential_assignments'),
        'affiliations' => config('persons.database.tables.affiliations', 'affiliations'),
    ];

    $expectedIndexes = [
        [$tables['persons'], 'persons_slug_unique'],
        [$tables['person_names'], 'person_names_primary_unique'],
        [$tables['person_names'], 'person_names_person_primary_index'],
        [$tables['title_assignments'], 'title_assignments_target_status_index'],
        [$tables['credential_assignments'], 'credential_assignments_target_status_index'],
        [$tables['affiliations'], 'affiliations_primary_unique'],
        [$tables['affiliations'], 'affiliations_target_primary_index'],
    ];

    foreach ($expectedIndexes as [$tableName, $indexName]) {
        expect(Schema::hasIndex($tableName, $indexName))->toBeTrue();
    }

    $migrationPath = dirname(__DIR__, 4)
        . '/packages/persons/database/migrations/2026_09_11_000001_add_identity_indexes_to_persons_tables.php';
    $migration = require $migrationPath;

    $migration->up();
    $migration->up();

    foreach ($expectedIndexes as [$tableName, $indexName]) {
        expect(Schema::hasIndex($tableName, $indexName))->toBeTrue();
    }
});

it('keeps primary application guards and rejects direct duplicate writes', function (): void {
    $person = Person::create([
        'name' => 'Indexed Identity',
        'slug' => 'indexed-identity',
    ]);

    $first = PersonName::create([
        'person_id' => $person->getKey(),
        'name_type' => PersonNameType::Display,
        'full_name' => 'First Display Name',
        'language_code' => 'en',
        'is_primary' => true,
    ]);
    $second = PersonName::create([
        'person_id' => $person->getKey(),
        'name_type' => PersonNameType::Display,
        'full_name' => 'Second Display Name',
        'language_code' => 'en',
        'is_primary' => true,
    ]);

    expect($first->fresh()?->is_primary)->toBeFalse()
        ->and($second->fresh()?->is_primary)->toBeTrue();

    expect(fn () => DB::table((new Person)->getTable())->insert([
        'id' => (string) Str::uuid(),
        'name' => 'Direct duplicate slug',
        'slug' => $person->slug,
        'searchable_name' => 'direct duplicate slug',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table((new PersonName)->getTable())->insert([
        'id' => (string) Str::uuid(),
        'person_id' => $person->getKey(),
        'name_type' => PersonNameType::Display->value,
        'full_name' => 'Direct duplicate primary',
        'language_code' => 'en',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('reports dirty slug data without deleting it during the dry-run preflight', function (): void {
    $tableName = (string) config('persons.database.tables.persons', 'persons');
    Schema::table($tableName, function ($table): void {
        $table->dropIndex('persons_slug_unique');
    });

    $slug = 'preflight-duplicate-' . Str::lower(Str::random(8));
    DB::table($tableName)->insert([
        [
            'id' => (string) Str::uuid(),
            'name' => 'Preflight One',
            'slug' => $slug,
            'searchable_name' => 'preflight one',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid(),
            'name' => 'Preflight Two',
            'slug' => $slug,
            'searchable_name' => 'preflight two',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $migrationPath = dirname(__DIR__, 4)
        . '/packages/persons/database/migrations/2026_09_11_000001_add_identity_indexes_to_persons_tables.php';
    $migration = require $migrationPath;
    $before = DB::table($tableName)->where('slug', $slug)->count();

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'No rows were deleted');

    expect(DB::table($tableName)->where('slug', $slug)->count())->toBe($before);
});
