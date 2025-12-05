<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $jsonType = config('filament-permissions.database.json_column_type', 'json');

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
                $table->index('parent_role_id', 'roles_parent_role_id_index');
            }
            if (Schema::hasColumn($rolesTable, 'template_id')) {
                $table->index('template_id', 'roles_template_id_index');
            }
            if (Schema::hasColumn($rolesTable, 'level')) {
                $table->index('level', 'roles_level_index');
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
