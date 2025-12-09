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
        $tableName = $tables['reservations'] ?? $tablePrefix . 'reservations';

        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('stockable');
            $table->string('cart_id')->index();
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            // Unique constraint: One reservation per product per cart
            $table->unique(['stockable_type', 'stockable_id', 'cart_id'], $tableName . '_stockable_cart_unique');
            $table->index(['stockable_type', 'stockable_id', 'expires_at'], $tableName . '_expiry_idx');
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

        Schema::dropIfExists($tables['reservations'] ?? $tablePrefix . 'reservations');
    }
};
