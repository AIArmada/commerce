<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id');
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->unsignedBigInteger('price')->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
