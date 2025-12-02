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
        Schema::create(config('inventory.table_names.levels', 'inventory_levels'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('inventoryable');
            $table->foreignUuid('location_id');
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('reorder_point')->nullable();
            $table->string('allocation_strategy')->nullable();
            $jsonType = config('inventory.json_column_type', 'json');
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['inventoryable_type', 'inventoryable_id', 'location_id'],
                'inventory_levels_inventoryable_location_unique'
            );
            $table->index('location_id');
            $table->index('quantity_on_hand');
            $table->index('reorder_point');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('inventory.table_names.levels', 'inventory_levels'));
    }
};
