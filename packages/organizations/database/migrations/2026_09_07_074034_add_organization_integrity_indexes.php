<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $organizationsTable = (string) config('organizations.database.tables.organizations', 'organizations');
        $membersTable = (string) config('organizations.database.tables.members', 'organization_members');

        $organizationsSlugUnique = 'organizations_slug_unique';

        if (Schema::hasTable($organizationsTable)) {
            if (! Schema::hasIndex($organizationsTable, ['slug'], 'unique')) {
                Schema::table($organizationsTable, function (Blueprint $table) use ($organizationsSlugUnique): void {
                    $table->unique('slug', $organizationsSlugUnique);
                });
            }

            $this->dropNonUniqueIndexes($organizationsTable, ['slug']);
        }

        $membersLookupUnique = 'organization_members_organization_user_unique';
        $membersRoleIndex = 'organization_members_organization_role_index';

        if (! Schema::hasTable($membersTable)) {
            return;
        }

        if (! Schema::hasIndex($membersTable, ['organization_id', 'user_id'], 'unique')) {
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

        if (Schema::hasTable($organizationsTable)) {
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

        if (! Schema::hasTable($membersTable)) {
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
};
