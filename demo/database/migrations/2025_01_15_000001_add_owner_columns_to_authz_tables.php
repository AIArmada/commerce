<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = config('filament-authz.database.table_prefix', 'authz_');
        $tables = [
            config('filament-authz.database.tables.audit_logs', 'authz_audit_logs'),
            config('filament-authz.database.tables.permission_snapshots', 'authz_permission_snapshots'),
            config('filament-authz.database.tables.permission_requests', 'authz_permission_requests'),
            config('filament-authz.database.tables.delegations', 'authz_delegations'),
            config('filament-authz.database.tables.scoped_permissions', 'authz_scoped_permissions'),
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (Schema::hasColumn($tableName, 'owner_type')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->nullableMorphs('owner');
            });
        }

        $identityTable = $tablePrefix.'identity_provider_mappings';

        if (! Schema::hasTable($identityTable)) {
            return;
        }

        if (! Schema::hasColumn($identityTable, 'owner_type')) {
            Schema::table($identityTable, function (Blueprint $table): void {
                $table->nullableMorphs('owner');
            });
        }

        if (Schema::hasIndex($identityTable, 'idp_mapping_unique')) {
            Schema::table($identityTable, function (Blueprint $table): void {
                $table->dropUnique('idp_mapping_unique');
            });
        }

        if (! Schema::hasIndex($identityTable, 'idp_mapping_unique')) {
            Schema::table($identityTable, function (Blueprint $table): void {
                $table->unique(['owner_type', 'owner_id', 'provider_type', 'provider_name', 'external_group'], 'idp_mapping_unique');
            });
        }
    }
};
