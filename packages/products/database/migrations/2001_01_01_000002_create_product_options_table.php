<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.options', 'product_options'), function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            $table->foreignUuid('product_id');

            $table->string('name'); // e.g., Size, Color, Material
            $table->string('display_name')->nullable(); // e.g., "Select your size"
            $table->unsignedInteger('position')->default(0);
            $table->string('visibility')->default('visible');
            $table->timestampTz('hidden_at')->nullable();

            $table->timestampsTz();

            $table->index(['product_id', 'position']);
            $table->index('visibility');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.options', 'product_options'));
    }
};
