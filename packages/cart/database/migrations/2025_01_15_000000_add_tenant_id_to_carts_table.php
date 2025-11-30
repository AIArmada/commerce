<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration only runs when cart tenancy is enabled.
     */
    public function up(): void
    {
        if (! config('cart.tenancy.enabled', false)) {
            return;
        }

        $tableName = config('cart.database.table', 'carts');
        $tenantColumn = config('cart.tenancy.column', 'tenant_id');

        if (Schema::hasColumn($tableName, $tenantColumn)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tenantColumn): void {
            $table->string($tenantColumn)->nullable()->after('identifier')->index();
        });

        // Update the unique constraint to include tenant_id
        Schema::table($tableName, function (Blueprint $table) use ($tenantColumn): void {
            $table->dropUnique(['identifier', 'instance']);
            $table->unique([$tenantColumn, 'identifier', 'instance']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! config('cart.tenancy.enabled', false)) {
            return;
        }

        $tableName = config('cart.database.table', 'carts');
        $tenantColumn = config('cart.tenancy.column', 'tenant_id');

        if (! Schema::hasColumn($tableName, $tenantColumn)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tenantColumn): void {
            $table->dropUnique([$tenantColumn, 'identifier', 'instance']);
            $table->unique(['identifier', 'instance']);
            $table->dropColumn($tenantColumn);
        });
    }
};
