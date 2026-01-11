<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        try {
            if ($driver === 'sqlite') {
                $rows = DB::select("PRAGMA index_list(\"{$table}\")");

                foreach ($rows as $row) {
                    $name = is_array($row) ? ($row['name'] ?? null) : ($row->name ?? null);

                    if ($name === $indexName) {
                        return true;
                    }
                }

                return false;
            }

            if ($driver === 'mysql') {
                $rows = DB::select("SHOW INDEX FROM `{$table}`");

                foreach ($rows as $row) {
                    $name = is_array($row) ? ($row['Key_name'] ?? null) : ($row->Key_name ?? null);

                    if ($name === $indexName) {
                        return true;
                    }
                }

                return false;
            }

            if ($driver === 'pgsql') {
                $rows = DB::select(
                    'select indexname from pg_indexes where schemaname = current_schema() and tablename = ?',
                    [$table],
                );

                foreach ($rows as $row) {
                    $name = is_array($row) ? ($row['indexname'] ?? null) : ($row->indexname ?? null);

                    if ($name === $indexName) {
                        return true;
                    }
                }

                return false;
            }
        } catch (\Throwable) {
            // Best-effort only. If we can't determine, fall through.
        }

        return false;
    }

    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $jsonType = config('filament-authz.database.json_column_type', 'json');

        // Skip if the roles table doesn't exist yet (will be created by Spatie Permission)
        if (! Schema::hasTable($rolesTable)) {
            return;
        }

        Schema::table($rolesTable, function (Blueprint $table) use ($jsonType, $rolesTable): void {
            if (! Schema::hasColumn($rolesTable, 'parent_role_id')) {
                $table->foreignUuid('parent_role_id')->nullable()->after('guard_name');
            }
            if (! Schema::hasColumn($rolesTable, 'template_id')) {
                $table->foreignUuid('template_id')->nullable()->after('parent_role_id');
            }
            if (! Schema::hasColumn($rolesTable, 'description')) {
                $table->text('description')->nullable()->after('template_id');
            }
            if (! Schema::hasColumn($rolesTable, 'level')) {
                $table->integer('level')->default(0)->after('description');
            }
            if (! Schema::hasColumn($rolesTable, 'metadata')) {
                $table->{$jsonType}('metadata')->nullable();
            }
            if (! Schema::hasColumn($rolesTable, 'is_system')) {
                $table->boolean('is_system')->default(false);
            }
            if (! Schema::hasColumn($rolesTable, 'is_assignable')) {
                $table->boolean('is_assignable')->default(true);
            }
        });

        // Add indexes separately to avoid issues
        Schema::table($rolesTable, function (Blueprint $table) use ($rolesTable): void {
            if (Schema::hasColumn($rolesTable, 'parent_role_id')) {
                if (! $this->indexExists($rolesTable, 'roles_parent_role_id_index')) {
                    $table->index('parent_role_id', 'roles_parent_role_id_index');
                }
            }
            if (Schema::hasColumn($rolesTable, 'template_id')) {
                if (! $this->indexExists($rolesTable, 'roles_template_id_index')) {
                    $table->index('template_id', 'roles_template_id_index');
                }
            }
            if (Schema::hasColumn($rolesTable, 'level')) {
                if (! $this->indexExists($rolesTable, 'roles_level_index')) {
                    $table->index('level', 'roles_level_index');
                }
            }
        });
    }

    public function down(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');

        // Skip if the roles table doesn't exist
        if (! Schema::hasTable($rolesTable)) {
            return;
        }

        Schema::table($rolesTable, function (Blueprint $table) use ($rolesTable): void {
            // Drop indexes first
            $table->dropIndex('roles_parent_role_id_index');
            $table->dropIndex('roles_template_id_index');
            $table->dropIndex('roles_level_index');

            // Drop columns
            $columnsToDrop = ['parent_role_id', 'template_id', 'description', 'level', 'metadata', 'is_system', 'is_assignable'];
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn($rolesTable, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
