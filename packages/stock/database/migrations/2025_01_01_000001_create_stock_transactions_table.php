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
        $tableName = $tables['transactions'] ?? $tablePrefix.'transactions';

        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('stockable');
            $table->uuid('user_id')->nullable();
            $table->integer('quantity');
            $table->enum('type', ['in', 'out']);
            $table->string('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamps();

            $table->index('type');
            $table->index('reason');
            $table->index('transaction_date');
            $table->index('user_id');
            $table->index(['stockable_type', 'stockable_id', 'type'], $tableName.'_stockable_type_idx');
            $table->index(['stockable_type', 'stockable_id', 'transaction_date'], $tableName.'_stockable_history_idx');
            $table->index(['user_id', 'transaction_date'], $tableName.'_user_history_idx');
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

        Schema::dropIfExists($tables['transactions'] ?? $tablePrefix.'transactions');
    }
};
