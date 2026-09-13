<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('persons.database.tables.titles', 'titles'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('category_id')->index();
            $table->string('name', 100);
            $table->string('short_form', 50)->nullable();
            $table->uuid('country_id')->nullable();
            $table->string('language_code', 10)->nullable();
            $table->string('usage_position', 20);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->index(['usage_position', 'sort_order']);
            $table->unique(
                ['category_id', 'usage_position', 'sort_order'],
                'titles_category_usage_sort_order_unique',
            );
        });
    }
};
