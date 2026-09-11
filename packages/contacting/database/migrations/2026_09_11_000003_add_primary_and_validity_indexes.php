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
        $contactMethodsTable = (string) config('contacting.database.tables.contact_methods', 'contact_methods');
        $socialProfilesTable = (string) config('contacting.database.tables.social_profiles', 'social_profiles');

        $hasContactMethods = $this->hasRequiredColumns(
            $contactMethodsTable,
            [
                'contactable_type',
                'contactable_id',
                'type',
                'purpose',
                'is_primary',
                'valid_from',
                'valid_until',
                'sort_order',
            ],
        );
        $hasSocialProfiles = $this->hasRequiredColumns(
            $socialProfilesTable,
            [
                'socialable_type',
                'socialable_id',
                'platform',
                'purpose',
                'is_primary',
                'valid_from',
                'valid_until',
                'sort_order',
            ],
        );

        if (! $hasContactMethods && ! $hasSocialProfiles) {
            return;
        }

        $driver = $this->supportedDriver();

        if ($hasContactMethods) {
            $this->assertNoDuplicateGroups(
                $contactMethodsTable,
                'primary contact methods',
                ['contactable_type', 'contactable_id', 'type', 'purpose'],
                static function (Builder $query): void {
                    $query->where('is_primary', true)
                        ->whereNotNull('contactable_type')
                        ->whereNotNull('contactable_id');
                },
            );
        }

        if ($hasSocialProfiles) {
            $this->assertNoDuplicateGroups(
                $socialProfilesTable,
                'primary social profiles',
                ['socialable_type', 'socialable_id', 'platform', 'purpose'],
                static function (Builder $query): void {
                    $query->where('is_primary', true)
                        ->whereNotNull('socialable_type')
                        ->whereNotNull('socialable_id');
                },
            );
        }

        if ($hasContactMethods) {
            $this->createPrimaryUniqueIndex(
                $contactMethodsTable,
                ['contactable_type', 'contactable_id', 'type', 'purpose'],
                'contactable_type',
                'contactable_id',
                $driver,
            );
            $this->addIndexIfMissing(
                $contactMethodsTable,
                ['contactable_type', 'contactable_id', 'is_primary'],
                $this->indexName($contactMethodsTable, 'contactable_primary_index'),
            );
            $this->addIndexIfMissing(
                $contactMethodsTable,
                [
                    'contactable_type',
                    'contactable_id',
                    'type',
                    'purpose',
                    'is_primary',
                    'valid_from',
                    'valid_until',
                    'sort_order',
                ],
                $this->indexName($contactMethodsTable, 'contactable_validity_index'),
            );
        }

        if ($hasSocialProfiles) {
            $this->createPrimaryUniqueIndex(
                $socialProfilesTable,
                ['socialable_type', 'socialable_id', 'platform', 'purpose'],
                'socialable_type',
                'socialable_id',
                $driver,
            );
            $this->addIndexIfMissing(
                $socialProfilesTable,
                ['socialable_type', 'socialable_id', 'is_primary'],
                $this->indexName($socialProfilesTable, 'socialable_primary_index'),
            );
            $this->addIndexIfMissing(
                $socialProfilesTable,
                [
                    'socialable_type',
                    'socialable_id',
                    'platform',
                    'purpose',
                    'is_primary',
                    'valid_from',
                    'valid_until',
                    'sort_order',
                ],
                $this->indexName($socialProfilesTable, 'socialable_validity_index'),
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
                'Contacting primary index migration cannot run because [%s] is missing column [%s].',
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
                'Contacting primary index migration cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }

        return $driver;
    }

    /**
     * @param  list<string>  $columns
     */
    private function createPrimaryUniqueIndex(
        string $tableName,
        array $columns,
        string $morphTypeColumn,
        string $morphIdColumn,
        string $driver,
    ): void {
        $indexName = $this->indexName($tableName, 'primary_unique');

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
                'CASE WHEN %s = 1 AND %s IS NOT NULL AND %s IS NOT NULL '
                . 'THEN CAST(CONCAT_WS(%s, %s) AS CHAR(512)) ELSE NULL END',
                $grammar->wrap('is_primary'),
                $grammar->wrap($morphTypeColumn),
                $grammar->wrap($morphIdColumn),
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
            ? sprintf(
                '%s IS TRUE AND %s IS NOT NULL AND %s IS NOT NULL',
                $grammar->wrap('is_primary'),
                $grammar->wrap($morphTypeColumn),
                $grammar->wrap($morphIdColumn),
            )
            : sprintf(
                '%s = 1 AND %s IS NOT NULL AND %s IS NOT NULL',
                $grammar->wrap('is_primary'),
                $grammar->wrap($morphTypeColumn),
                $grammar->wrap($morphIdColumn),
            );

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
            'Contacting primary index dry-run preflight blocked [%s]: %d duplicate %s groups. '
            . 'Samples: %s. No rows were deleted; resolve the conflicts and rerun the migration.',
            $tableName,
            $groups->count(),
            $description,
            json_encode($samples, JSON_THROW_ON_ERROR),
        ));
    }

    private function indexName(string $tableName, string $suffix): string
    {
        return str_replace(['.', '-', ' '], '_', $tableName) . '_' . $suffix;
    }
};
