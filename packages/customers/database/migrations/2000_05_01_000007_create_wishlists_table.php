<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('customers.database.tables.wishlists', 'wishlists'), function (Blueprint $table): void {
            $jsonColumnType = config('customers.database.json_column_type', 'json');

            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            $table->foreignUuid('customer_id');

            $table->string('name')->default('My Wishlist');
            $table->text('description')->nullable();

            // Sharing
            $table->boolean('is_public')->default(false);
            $table->string('share_token', 64)->unique();

            // Default wishlist
            $table->boolean('is_default')->default(false);

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
            $table->index('share_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('customers.database.tables.wishlists', 'wishlists'));
    }
};
