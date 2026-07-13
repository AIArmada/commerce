<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.collections', 'product_collections'), function (Blueprint $table): void {
            $jsonColumnType = commerce_json_column_type('products', 'jsonb');

            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');
            $table->string('owner_scope', 64)->default('global');

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            // Type: manual or automatic (rule-based)
            $table->string('type')->default('manual');

            // For automatic collections
            $table->{$jsonColumnType}('conditions')->nullable();

            // Display
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('active');
            $table->string('visibility')->default('catalog');
            $table->timestampTz('hidden_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            // Scheduling
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('unpublished_at')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestampsTz();

            $table->unique(['owner_scope', 'slug']);

            $table->index('status');
            $table->index('hidden_at');
            $table->index('is_featured');
            $table->index(['published_at', 'unpublished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.collections', 'product_collections'));
    }
};
