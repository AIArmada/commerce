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
        Schema::table(config('stock.table_name', 'stock_transactions'), function (Blueprint $table): void {
            $table->nullableUuidMorphs('owner');

            $table->index(['owner_type', 'owner_id'], 'stock_transactions_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('stock.table_name', 'stock_transactions'), function (Blueprint $table): void {
            $table->dropIndex('stock_transactions_owner_idx');
            $table->dropMorphs('owner');
        });
    }
};
