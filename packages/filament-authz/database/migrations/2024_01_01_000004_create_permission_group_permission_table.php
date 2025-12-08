<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-authz.database.tables.permission_group_permission', 'authz_permission_group_permission');
        $permissionGroupsTable = config('filament-authz.database.tables.permission_groups', 'authz_permission_groups');
        $permissionsTable = config('permission.table_names.permissions', 'permissions');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->foreignUuid('permission_group_id');
            $table->foreignUuid('permission_id');

            $table->primary(['permission_group_id', 'permission_id'], 'authz_group_authz_primary');
            $table->index('permission_id', 'authz_group_authz_permission_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-authz.database.tables.permission_group_permission', 'authz_permission_group_permission'));
    }
};
