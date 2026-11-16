<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $componentTableName = (string) config('products.database.tables.product_components', 'product_components');

        Schema::create($componentTableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            // The composite parent and each exact sellable component.
            $table->foreignUuid('product_id');
            $table->foreignUuid('parent_variant_id')->nullable(); // null = product-level composition
            $table->foreignUuid('component_product_id')->nullable();
            $table->string('buyable_type')->nullable(); // registered Eloquent Buyable morph alias
            $table->string('buyable_id')->nullable();
            $table->foreignUuid('variant_id')->nullable();

            $table->unsignedInteger('quantity');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedBigInteger('unit_price')->nullable(); // sum-mode per-unit override, minor units
            $jsonColumnType = commerce_json_column_type('products', 'jsonb');
            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestampsTz();

            $table->index(['product_id', 'position']);
            $table->index('component_product_id');
            $table->index('variant_id');
            $table->index(['product_id', 'parent_variant_id']);
            $table->index(['buyable_type', 'buyable_id']);
            $table->unique(['product_id', 'buyable_type', 'buyable_id'], 'product_components_parent_buyable_unique');
            $table->unique(
                ['product_id', 'component_product_id', 'variant_id'],
                'product_components_parent_component_variant_unique',
            );
        });
    }
};
