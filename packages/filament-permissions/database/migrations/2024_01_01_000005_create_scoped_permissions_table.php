<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-permissions.database.tables.scoped_permissions', 'perm_scoped_permissions');
        $jsonType = config('filament-permissions.database.json_column_type', 'json');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('permission_id');
            $table->uuidMorphs('permissionable'); // User or Role
            $table->string('scope_type'); // team, tenant, resource, temporal
            $table->uuid('scope_id')->nullable();
            $table->string('scope_model')->nullable();
            $table->{$jsonType}('conditions')->nullable();
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('granted_by')->nullable();
            $table->timestamps();

            $table->index('permission_id');
            $table->index(['permissionable_type', 'permissionable_id'], 'scoped_perm_permissionable_index');
            $table->index(['scope_type', 'scope_id'], 'scoped_perm_scope_index');
            $table->index('expires_at');
            $table->index('granted_at');
            $table->index(['permissionable_type', 'permissionable_id', 'scope_type'], 'scoped_perm_full_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-permissions.database.tables.scoped_permissions', 'perm_scoped_permissions'));
    }
};
