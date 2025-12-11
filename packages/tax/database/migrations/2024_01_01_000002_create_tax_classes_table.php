<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('tax.tables.tax_classes', 'tax_classes'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Flags
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('tax.tables.tax_classes', 'tax_classes'));
    }
};
