<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $database = config('stock.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'stock_';
        $tables = $database['tables'] ?? [];
        $tableName = $tables['transactions'] ?? $tablePrefix . 'transactions';

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $table->nullableUuidMorphs('owner');

            $table->index(['owner_type', 'owner_id'], $tableName . '_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $database = config('stock.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'stock_';
        $tables = $database['tables'] ?? [];
        $tableName = $tables['transactions'] ?? $tablePrefix . 'transactions';

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $table->dropIndex($tableName . '_owner_idx');
            $table->dropMorphs('owner');
        });
    }
};
