<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.tables.category_product', 'category_product'), function (Blueprint $table): void {
            $table->foreignUuid('category_id')
                ->constrained(config('products.tables.categories', 'product_categories'))
                ->cascadeOnDelete();

            $table->foreignUuid('product_id')
                ->constrained(config('products.tables.products', 'products'))
                ->cascadeOnDelete();

            $table->timestamps();

            $table->primary(['category_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.tables.category_product', 'category_product'));
    }
};
