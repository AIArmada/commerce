<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('customers.database.tables.wishlist_items', 'wishlist_items'), function (Blueprint $table): void {
            $jsonColumnType = config('customers.database.json_column_type', 'json');

            $table->uuid('id')->primary();

            $table->foreignUuid('wishlist_id');

            // Polymorphic product reference
            $table->uuidMorphs('product');

            // When added
            $table->timestamp('added_at');

            // Notification flags
            $table->boolean('notified_on_sale')->default(false);
            $table->boolean('notified_in_stock')->default(false);

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            // Unique per product in wishlist
            $table->unique(['wishlist_id', 'product_type', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('customers.database.tables.wishlist_items', 'wishlist_items'));
    }
};
