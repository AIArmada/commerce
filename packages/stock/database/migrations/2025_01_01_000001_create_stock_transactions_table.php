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
        Schema::create(config('stock.table_name', 'stock_transactions'), function (Blueprint $table): void {
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
            $table->index(['stockable_type', 'stockable_id', 'type'], 'stock_transactions_stockable_type_idx');
            $table->index(['stockable_type', 'stockable_id', 'transaction_date'], 'stock_transactions_stockable_history_idx');
            $table->index(['user_id', 'transaction_date'], 'stock_transactions_user_history_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('stock.table_name', 'stock_transactions'));
    }
};
