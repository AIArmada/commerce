<?php

declare(strict_types=1);

use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Models\Person;
use AIArmada\Persons\Models\PersonName;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('ships the identity and assignment indexes in the base creates', function (): void {
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

    $migrationBase = dirname(__DIR__, 4) . '/packages/persons/database/migrations/';

    $contents = [
        '2000_01_01_000001_create_persons_table.php' => ['persons_slug_unique', 'Schema::create'],
        '2000_01_01_000002_create_person_names_table.php' => ['person_names_primary_unique', 'person_names_person_primary_index'],
        '2000_01_01_000006_create_title_assignments_table.php' => ['title_assignments_target_status_index'],
        '2000_01_01_000008_create_credential_assignments_table.php' => ['credential_assignments_target_status_index'],
        '2000_01_01_000009_create_affiliations_table.php' => ['affiliations_primary_unique', 'affiliations_target_primary_index'],
    ];

    foreach ($contents as $migrationFile => $needles) {
        $create = (string) file_get_contents($migrationBase . $migrationFile);

        foreach ($needles as $needle) {
            expect($create)->toContain($needle);
        }

        expect($create)->not->toContain('hasTable')
            ->and($create)->not->toContain('hasIndex');
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
