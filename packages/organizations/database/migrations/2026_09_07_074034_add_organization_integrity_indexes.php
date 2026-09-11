<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $organizationsTable = (string) config('organizations.database.tables.organizations', 'organizations');
        $membersTable = (string) config('organizations.database.tables.members', 'organization_members');

        $organizationsSlugUnique = 'organizations_slug_unique';
        $hasOrganizations = Schema::hasTable($organizationsTable);
        $hasMembers = Schema::hasTable($membersTable);

        if ($hasOrganizations) {
            $this->assertRequiredColumns($organizationsTable, ['slug']);
        }

        if ($hasMembers) {
            $this->assertRequiredColumns($membersTable, ['organization_id', 'user_id', 'role']);
        }

        if ($hasOrganizations) {
            $this->assertNoDuplicateGroups(
                $organizationsTable,
                'organization slugs',
                ['slug'],
                static function (Builder $query): void {
                    $query->whereNotNull('slug');
                },
            );
        }

        if ($hasMembers) {
            $this->assertNoDuplicateGroups(
                $membersTable,
                'organization memberships',
                ['organization_id', 'user_id'],
                static function (Builder $query): void {},
            );
        }

        if ($hasOrganizations) {
            if (! Schema::hasIndex($organizationsTable, $organizationsSlugUnique)
                && ! Schema::hasIndex($organizationsTable, ['slug'], 'unique')) {
                Schema::table($organizationsTable, function (Blueprint $table) use ($organizationsSlugUnique): void {
                    $table->unique('slug', $organizationsSlugUnique);
                });
            }

            $this->dropNonUniqueIndexes($organizationsTable, ['slug']);
        }

        $membersLookupUnique = 'organization_members_organization_user_unique';
        $membersRoleIndex = 'organization_members_organization_role_index';

        if (! $hasMembers) {
            return;
        }

        if (! Schema::hasIndex($membersTable, $membersLookupUnique)
            && ! Schema::hasIndex($membersTable, ['organization_id', 'user_id'], 'unique')) {
            Schema::table($membersTable, function (Blueprint $table) use ($membersLookupUnique): void {
                $table->unique(['organization_id', 'user_id'], $membersLookupUnique);
            });
        }

        $this->dropNonUniqueIndexes($membersTable, ['organization_id', 'user_id']);

        if (! Schema::hasIndex($membersTable, $membersRoleIndex)) {
            Schema::table($membersTable, function (Blueprint $table) use ($membersRoleIndex): void {
                $table->index(['organization_id', 'role'], $membersRoleIndex);
            });
        }
    }

    public function down(): void
    {
        $organizationsTable = (string) config('organizations.database.tables.organizations', 'organizations');
        $membersTable = (string) config('organizations.database.tables.members', 'organization_members');

        $organizationsSlugUnique = 'organizations_slug_unique';

        if (Schema::hasTable($organizationsTable) && Schema::hasColumn($organizationsTable, 'slug')) {
            if (Schema::hasIndex($organizationsTable, $organizationsSlugUnique)) {
                Schema::table($organizationsTable, function (Blueprint $table) use ($organizationsSlugUnique): void {
                    $table->dropUnique($organizationsSlugUnique);
                });
            }

            if (! Schema::hasIndex($organizationsTable, ['slug'])) {
                Schema::table($organizationsTable, function (Blueprint $table): void {
                    $table->index('slug');
                });
            }
        }

        if (! Schema::hasTable($membersTable)
            || ! Schema::hasColumn($membersTable, 'organization_id')
            || ! Schema::hasColumn($membersTable, 'user_id')
            || ! Schema::hasColumn($membersTable, 'role')) {
            return;
        }

        $membersLookupUnique = 'organization_members_organization_user_unique';
        $membersRoleIndex = 'organization_members_organization_role_index';

        if (Schema::hasIndex($membersTable, $membersLookupUnique)) {
            Schema::table($membersTable, function (Blueprint $table) use ($membersLookupUnique): void {
                $table->dropUnique($membersLookupUnique);
            });
        }

        if (Schema::hasIndex($membersTable, $membersRoleIndex)) {
            Schema::table($membersTable, function (Blueprint $table) use ($membersRoleIndex): void {
                $table->dropIndex($membersRoleIndex);
            });
        }

        if (! Schema::hasIndex($membersTable, ['organization_id', 'user_id'])) {
            Schema::table($membersTable, function (Blueprint $table): void {
                $table->index(['organization_id', 'user_id']);
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropNonUniqueIndexes(string $tableName, array $columns): void
    {
        foreach (Schema::getIndexes($tableName) as $index) {
            if (($index['columns'] ?? null) !== $columns
                || ($index['unique'] ?? false)
                || ($index['primary'] ?? false)) {
                continue;
            }

            $indexName = $index['name'] ?? null;

            if (! is_string($indexName) || $indexName === '') {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertRequiredColumns(string $tableName, array $columns): void
    {
        foreach ($columns as $columnName) {
            if (Schema::hasColumn($tableName, $columnName)) {
                continue;
            }

            throw new RuntimeException(sprintf(
                'Organization integrity index migration cannot run because [%s] is missing column [%s].',
                $tableName,
                $columnName,
            ));
        }
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
            'Organization integrity index dry-run preflight blocked [%s]: %d duplicate %s groups. '
            . 'Samples: %s. No rows were deleted; resolve the conflicts and rerun the migration.',
            $tableName,
            $groups->count(),
            $description,
            json_encode($samples, JSON_THROW_ON_ERROR),
        ));
    }
};
