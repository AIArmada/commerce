<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $databaseConfig = config('cashier-chip.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cashier_chip_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['payment_methods'] ?? $tablePrefix . 'payment_methods';
        $jsonColumnType = $databaseConfig['json_column_type'] ?? 'jsonb';

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($jsonColumnType, $tableName): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->uuidMorphs('billable');
            $table->string('recurring_token');
            $table->string('type')->nullable();
            $table->string('brand')->nullable();
            $table->string('last_four', 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->{$jsonColumnType}('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['billable_type', 'billable_id', 'recurring_token'], $tableName . '_billable_token_unique');
            $table->index(['billable_type', 'billable_id', 'is_default'], $tableName . '_billable_default_idx');
            $table->index('recurring_token');
        });
    }

    public function down(): void
    {
        $databaseConfig = config('cashier-chip.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cashier_chip_';
        $tables = $databaseConfig['tables'] ?? [];

        Schema::dropIfExists($tables['payment_methods'] ?? $tablePrefix . 'payment_methods');
    }
};
