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
        $tableName = config('stock.reservations_table', 'stock_reservations');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('stockable');
            $table->string('cart_id')->index();
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            // Unique constraint: One reservation per product per cart
            $table->unique(['stockable_type', 'stockable_id', 'cart_id'], 'stock_reservations_unique');
            $table->index(['stockable_type', 'stockable_id', 'expires_at'], 'stock_reservations_expiry_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('stock.reservations_table', 'stock_reservations'));
    }
};
