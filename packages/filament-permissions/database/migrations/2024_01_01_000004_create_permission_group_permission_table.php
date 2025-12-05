<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-permissions.database.tables.permission_group_permission', 'perm_permission_group_permission');
        $permissionGroupsTable = config('filament-permissions.database.tables.permission_groups', 'perm_permission_groups');
        $permissionsTable = config('permission.table_names.permissions', 'permissions');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->foreignUuid('permission_group_id');
            $table->foreignUuid('permission_id');

            $table->primary(['permission_group_id', 'permission_id'], 'perm_group_perm_primary');
            $table->index('permission_id', 'perm_group_perm_permission_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-permissions.database.tables.permission_group_permission', 'perm_permission_group_permission'));
    }
};
