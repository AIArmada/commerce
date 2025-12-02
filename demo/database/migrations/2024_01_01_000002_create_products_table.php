<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('sku')->nullable()->unique();
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('compare_at_price')->nullable();
            $table->string('currency', 3)->default('MYR');
            $table->boolean('is_active')->default(true);
            $table->boolean('track_stock')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->foreignUuid('category_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('is_active');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
