<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create cart_events table for event sourcing.
     *
     * This table stores all cart-related events for audit trails,
     * event replay, and analytics. Non-breaking addition.
     */
    public function up(): void
    {
        $tableName = config('cart.database.events_table', 'cart_events');
        $cartsTable = config('cart.database.table', 'carts');
        $jsonType = (string) commerce_json_column_type('cart', 'json');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();

            // Reference to cart (not constrained per guidelines)
            $table->foreignUuid('cart_id')->index();

            // Event identification
            $table->string('event_type', 100)->index();
            $table->uuid('event_id')->unique();

            // Event data
            $table->{$jsonType}('payload');
            $table->{$jsonType}('metadata')->nullable();

            // Versioning for event sourcing
            $table->unsignedBigInteger('aggregate_version');
            $table->unsignedBigInteger('stream_position');

            // Timing
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            // Composite indexes for efficient queries
            $table->index(['cart_id', 'stream_position'], 'idx_cart_events_stream');
            $table->index(['cart_id', 'aggregate_version'], 'idx_cart_events_version');
            $table->index(['event_type', 'occurred_at'], 'idx_cart_events_type_time');
        });

        // Add GIN indexes for PostgreSQL jsonb columns
        if (
            $jsonType === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$tableName}_payload_gin_index ON \"{$tableName}\" USING GIN (\"payload\")");
            DB::statement("CREATE INDEX IF NOT EXISTS {$tableName}_metadata_gin_index ON \"{$tableName}\" USING GIN (\"metadata\")");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('cart.database.events_table', 'cart_events');

        Schema::dropIfExists($tableName);
    }
};
