<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.categories', 'product_categories'), function (Blueprint $table): void {
            $jsonColumnType = commerce_json_column_type('products', 'jsonb');

            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');
            $table->string('owner_scope', 64)->default('global');

            // Parent for hierarchy
            $table->foreignUuid('parent_id')->nullable();
            $table->string('parent_scope', 36)->default('root');

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            // Display
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('active');
            $table->string('visibility')->default('catalog');
            $table->timestampTz('hidden_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestampsTz();

            // Unique slug per parent
            $table->unique(['owner_scope', 'parent_scope', 'slug']);
            $table->index('parent_id');
            $table->index('status');
            $table->index('hidden_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.categories', 'product_categories'));
    }
};
