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

        $organizationsSlugIndex = $organizationsTable . '_slug_index';
        $organizationsSlugUnique = 'organizations_slug_unique';

        if (Schema::hasTable($organizationsTable)) {
            if (Schema::hasIndex($organizationsTable, $organizationsSlugIndex)) {
                Schema::table($organizationsTable, function (Blueprint $table) use ($organizationsSlugIndex): void {
                    $table->dropIndex($organizationsSlugIndex);
                });
            }

            if (! Schema::hasIndex($organizationsTable, $organizationsSlugUnique)) {
                Schema::table($organizationsTable, function (Blueprint $table) use ($organizationsSlugUnique): void {
                    $table->unique('slug', $organizationsSlugUnique);
                });
            }
        }

        $membersLookupIndex = $membersTable . '_organization_id_user_id_index';
        $membersLookupUnique = 'organization_members_organization_user_unique';
        $membersRoleIndex = 'organization_members_organization_role_index';

        if (! Schema::hasTable($membersTable)) {
            return;
        }

        if (Schema::hasIndex($membersTable, $membersLookupIndex)) {
            Schema::table($membersTable, function (Blueprint $table) use ($membersLookupIndex): void {
                $table->dropIndex($membersLookupIndex);
            });
        }

        if (! Schema::hasIndex($membersTable, $membersLookupUnique)) {
            Schema::table($membersTable, function (Blueprint $table) use ($membersLookupUnique): void {
                $table->unique(['organization_id', 'user_id'], $membersLookupUnique);
            });
        }

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

        $organizationsSlugIndex = $organizationsTable . '_slug_index';
        $organizationsSlugUnique = 'organizations_slug_unique';

        if (Schema::hasTable($organizationsTable)) {
            if (Schema::hasIndex($organizationsTable, $organizationsSlugUnique)) {
                Schema::table($organizationsTable, function (Blueprint $table) use ($organizationsSlugUnique): void {
                    $table->dropUnique($organizationsSlugUnique);
                });
            }

            if (! Schema::hasIndex($organizationsTable, $organizationsSlugIndex)) {
                Schema::table($organizationsTable, function (Blueprint $table): void {
                    $table->index('slug');
                });
            }
        }

        if (! Schema::hasTable($membersTable)) {
            return;
        }

        $membersLookupIndex = $membersTable . '_organization_id_user_id_index';
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

        if (! Schema::hasIndex($membersTable, $membersLookupIndex)) {
            Schema::table($membersTable, function (Blueprint $table): void {
                $table->index(['organization_id', 'user_id']);
            });
        }
    }
};
