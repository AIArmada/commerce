<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add AI & Analytics columns to carts table for abandonment tracking.
     *
     * All columns are nullable or have defaults - non-breaking change.
     */
    public function up(): void
    {
        $tableName = config('cart.database.table', 'carts');

        Schema::table($tableName, function (Blueprint $table): void {
            // Track user engagement timing
            $table->timestamp('last_activity_at')
                ->nullable()
                ->after('expires_at')
                ->index('idx_carts_last_activity');

            // Conversion funnel tracking
            $table->timestamp('checkout_started_at')
                ->nullable()
                ->after('last_activity_at');

            // Abandonment tracking
            $table->timestamp('checkout_abandoned_at')
                ->nullable()
                ->after('checkout_started_at');

            // Recovery email tracking
            $table->unsignedTinyInteger('recovery_attempts')
                ->default(0)
                ->after('checkout_abandoned_at');

            // Recovery success tracking
            $table->timestamp('recovered_at')
                ->nullable()
                ->after('recovery_attempts');
        });

        // Add composite index for abandoned cart queries
        Schema::table($tableName, function (Blueprint $table): void {
            $table->index(
                ['checkout_abandoned_at', 'recovery_attempts'],
                'idx_carts_abandonment_recovery'
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
            $table->dropIndex('idx_carts_last_activity');
            $table->dropIndex('idx_carts_abandonment_recovery');

            $table->dropColumn([
                'last_activity_at',
                'checkout_started_at',
                'checkout_abandoned_at',
                'recovery_attempts',
                'recovered_at',
            ]);
        });
    }
};
