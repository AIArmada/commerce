<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'persons' => (string) config('persons.database.tables.persons', 'persons'),
            'person_names' => (string) config('persons.database.tables.person_names', 'person_names'),
            'title_assignments' => (string) config('persons.database.tables.title_assignments', 'title_assignments'),
            'credential_assignments' => (string) config('persons.database.tables.credential_assignments', 'credential_assignments'),
            'affiliations' => (string) config('persons.database.tables.affiliations', 'affiliations'),
        ];

        $hasPersons = $this->hasRequiredColumns($tables['persons'], ['slug']);
        $hasPersonNames = $this->hasRequiredColumns(
            $tables['person_names'],
            ['person_id', 'name_type', 'language_code', 'is_primary'],
        );
        $hasTitleAssignments = $this->hasRequiredColumns(
            $tables['title_assignments'],
            ['titleable_type', 'titleable_id', 'status'],
        );
        $hasCredentialAssignments = $this->hasRequiredColumns(
            $tables['credential_assignments'],
            ['credentialable_type', 'credentialable_id', 'status'],
        );
        $hasAffiliations = $this->hasRequiredColumns(
            $tables['affiliations'],
            ['affiliatable_type', 'affiliatable_id', 'is_primary'],
        );

        if ($hasPersons) {
            $this->assertNoDuplicateGroups(
                $tables['persons'],
                'person slugs',
                ['slug'],
                static function (Builder $query): void {
                    $query->whereNotNull('slug');
                },
            );
        }

        if ($hasPersonNames) {
            $this->assertNoDuplicateGroups(
                $tables['person_names'],
                'primary person names',
                ['person_id', 'name_type', 'language_code'],
                static function (Builder $query): void {
                    $query->where('is_primary', true);
                },
            );
        }

        if ($hasAffiliations) {
            $this->assertNoDuplicateGroups(
                $tables['affiliations'],
                'primary affiliations',
                ['affiliatable_type', 'affiliatable_id'],
                static function (Builder $query): void {
                    $query->where('is_primary', true);
                },
            );
        }

        $driver = ($hasPersons || $hasPersonNames || $hasAffiliations)
            ? $this->supportedDriver()
            : null;

        if ($hasPersons && $driver !== null) {
            $this->createPersonSlugIndex($tables['persons'], $driver);
        }

        if ($hasPersonNames && $driver !== null) {
            $this->createConditionalUniqueIndex(
                $tables['person_names'],
                'person_names_primary_unique',
                ['person_id', 'name_type', 'language_code'],
                'is_primary',
                $driver,
            );
            $this->addIndexIfMissing(
                $tables['person_names'],
                ['person_id', 'is_primary'],
                'person_names_person_primary_index',
            );
        }

        if ($hasTitleAssignments) {
            $this->addIndexIfMissing(
                $tables['title_assignments'],
                ['titleable_type', 'titleable_id', 'status'],
                'title_assignments_target_status_index',
            );
        }

        if ($hasCredentialAssignments) {
            $this->addIndexIfMissing(
                $tables['credential_assignments'],
                ['credentialable_type', 'credentialable_id', 'status'],
                'credential_assignments_target_status_index',
            );
        }

        if ($hasAffiliations && $driver !== null) {
            $this->createConditionalUniqueIndex(
                $tables['affiliations'],
                'affiliations_primary_unique',
                ['affiliatable_type', 'affiliatable_id'],
                'is_primary',
                $driver,
            );
            $this->addIndexIfMissing(
                $tables['affiliations'],
                ['affiliatable_type', 'affiliatable_id', 'is_primary'],
                'affiliations_target_primary_index',
            );
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasRequiredColumns(string $tableName, array $columns): bool
    {
        if (! Schema::hasTable($tableName)) {
            return false;
        }

        foreach ($columns as $columnName) {
            if (Schema::hasColumn($tableName, $columnName)) {
                continue;
            }

            throw new RuntimeException(sprintf(
                'Persons identity index migration cannot run because [%s] is missing column [%s].',
                $tableName,
                $columnName,
            ));
        }

        return true;
    }

    private function supportedDriver(): string
    {
        $driver = ConnectionDriver::name(Schema::getConnection());

        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Persons identity index migration cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }

        return $driver;
    }

    private function createPersonSlugIndex(string $tableName, string $driver): void
    {
        if (Schema::hasIndex($tableName, 'persons_slug_unique')
            || Schema::hasIndex($tableName, ['slug'], 'unique')) {
            return;
        }

        if ($driver === 'mysql') {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique('slug', 'persons_slug_unique');
            });

            return;
        }

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX IF NOT EXISTS %s ON %s (%s) WHERE %s IS NOT NULL',
            $grammar->wrap('persons_slug_unique'),
            $grammar->wrapTable($tableName),
            $grammar->wrap('slug'),
            $grammar->wrap('slug'),
        ));
    }

    /**
     * @param  list<string>  $columns
     */
    private function createConditionalUniqueIndex(
        string $tableName,
        string $indexName,
        array $columns,
        string $conditionColumn,
        string $driver,
    ): void {
        if (Schema::hasIndex($tableName, $indexName)
            || Schema::hasIndex($tableName, $columns, 'unique')) {
            return;
        }

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();

        if ($driver === 'mysql') {
            $wrappedColumns = implode(', ', array_map(
                $grammar->wrap(...),
                $columns,
            ));
            $expression = sprintf(
                'CASE WHEN %s = 1 THEN CAST(CONCAT_WS(%s, %s) AS CHAR(512)) ELSE NULL END',
                $grammar->wrap($conditionColumn),
                $connection->getPdo()->quote('|'),
                $wrappedColumns,
            );

            $connection->statement(sprintf(
                'CREATE UNIQUE INDEX %s ON %s ((%s))',
                $grammar->wrap($indexName),
                $grammar->wrapTable($tableName),
                $expression,
            ));

            return;
        }

        $predicate = $driver === 'pgsql'
            ? sprintf('%s IS TRUE', $grammar->wrap($conditionColumn))
            : sprintf('%s = 1', $grammar->wrap($conditionColumn));

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX IF NOT EXISTS %s ON %s (%s) WHERE %s',
            $grammar->wrap($indexName),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), $columns)),
            $predicate,
        ));
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if (Schema::hasIndex($tableName, $indexName)
            || Schema::hasIndex($tableName, $columns)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    /**
     * @param  list<string>  $columns
     * @param  callable(Builder): void  $filter
     */
    private function assertNoDuplicateGroups(
        string $tableName,
        string $description,
        array $columns,
        callable $filter,
    ): void {
        $query = DB::table($tableName);
        $filter($query);

        $groups = $query
            ->select($columns)
            ->selectRaw('COUNT(*) AS duplicate_count')
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($groups->isEmpty()) {
            return;
        }

        $samples = $groups->take(10)->map(function (object $group) use ($columns): array {
            $sample = [];

            foreach ($columns as $column) {
                $sample[$column] = $group->{$column};
            }

            $sample['count'] = (int) $group->duplicate_count;

            return $sample;
        })->values()->all();

        throw new RuntimeException(sprintf(
            'Persons identity index dry-run preflight blocked [%s]: %d duplicate %s groups. '
            . 'Samples: %s. No rows were deleted; resolve the conflicts and rerun the migration.',
            $tableName,
            $groups->count(),
            $description,
            json_encode($samples, JSON_THROW_ON_ERROR),
        ));
    }
};
