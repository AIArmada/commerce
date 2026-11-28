<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('customers.database.tables.segments', 'customer_segments'), function (Blueprint $table): void {
            $jsonColumnType = commerce_json_column_type('customers', 'jsonb');

            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');
            $table->string('owner_scope', 64)->default('global');

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            // Type
            $table->string('type')->default('custom'); // loyalty, behavior, demographic, custom

            // Automatic segment conditions (JSON rules)
            $table->{$jsonColumnType}('conditions')->nullable();
            $table->boolean('is_automatic')->default(true);

            // Priority for pricing (higher = more important)
            $table->integer('priority')->default(0);

            // Status
            $table->timestampTz('deactivated_at')->nullable();

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestampsTz();

            // Indexes
            $table->unique(['owner_scope', 'slug'], 'customers_segments_owner_slug_unique');
            $table->index(['deactivated_at', 'priority']);
            $table->index(['owner_type', 'owner_id', 'deactivated_at'], 'customer_segments_owner_deactivated_index');
            $table->index('type');
        });
    }
};
