<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.tables.variant_options', 'product_variant_options'), function (Blueprint $table): void {
            $table->foreignUuid('variant_id')
                ->constrained(config('products.tables.variants', 'product_variants'))
                ->cascadeOnDelete();

            $table->foreignUuid('option_value_id')
                ->constrained(config('products.tables.option_values', 'product_option_values'))
                ->cascadeOnDelete();

            $table->primary(['variant_id', 'option_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.tables.variant_options', 'product_variant_options'));
    }
};
