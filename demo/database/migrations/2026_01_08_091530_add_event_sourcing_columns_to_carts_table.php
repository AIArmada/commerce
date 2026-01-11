<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Event Sourcing preparation columns to carts table.
     *
     * These columns enable future event sourcing without breaking changes.
     * All columns have defaults - non-breaking change.
     */
    public function up(): void
    {
        $tableName = config('cart.database.table', 'carts');

        Schema::table($tableName, function (Blueprint $table): void {
            // Position in cart event stream for replay
            $table->unsignedBigInteger('event_stream_position')
                ->default(0)
                ->after('version');

            // Schema version for aggregate migrations
            $table->string('aggregate_version', 10)
                ->default('1.0')
                ->after('event_stream_position');

            // When last snapshot was taken
            $table->timestamp('snapshot_at')
                ->nullable()
                ->after('aggregate_version');
        });

        // Add index for event replay queries
        Schema::table($tableName, function (Blueprint $table): void {
            $table->index(
                ['id', 'event_stream_position'],
                'idx_carts_event_stream'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('cart.database.table', 'carts');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex('idx_carts_event_stream');

            $table->dropColumn([
                'event_stream_position',
                'aggregate_version',
                'snapshot_at',
            ]);
        });
    }
};
