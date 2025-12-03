<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether to wrap the migration in a transaction.
     *
     * Disabled to allow CONCURRENTLY index creation on PostgreSQL.
     */
    public $withinTransaction = false;

    /**
     * Add performance indexes to carts table.
     *
     * These indexes optimize common query patterns without schema changes.
     */
    public function up(): void
    {
        $tableName = config('cart.database.table', 'carts');
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->addPostgreSQLIndexes($tableName);
        } else {
            $this->addMySQLIndexes($tableName);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('cart.database.table', 'carts');
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_carts_lookup_covering');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_carts_active');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_carts_expired');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_carts_analytics');
        } else {
            DB::statement("DROP INDEX idx_carts_lookup_covering ON `{$tableName}`");
            DB::statement("DROP INDEX idx_carts_expired ON `{$tableName}`");
            DB::statement("DROP INDEX idx_carts_analytics ON `{$tableName}`");
        }
    }

    /**
     * PostgreSQL-specific indexes with advanced features.
     */
    private function addPostgreSQLIndexes(string $tableName): void
    {
        // Covering index for primary lookup (avoids table access)
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_lookup_covering
            ON \"{$tableName}\" (identifier, instance)
            INCLUDE (id, version, updated_at, expires_at)
        ");

        // Partial index for active (non-expired) carts
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_active
            ON \"{$tableName}\" (identifier, instance)
            WHERE expires_at IS NULL OR expires_at > NOW()
        ");

        // Index for cleanup job (expired carts)
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_expired
            ON \"{$tableName}\" (expires_at)
            WHERE expires_at IS NOT NULL
        ");

        // Index for abandonment analytics (non-empty carts)
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_analytics
            ON \"{$tableName}\" (updated_at, instance)
            WHERE items IS NOT NULL AND items != '[]'::jsonb
        ");
    }

    /**
     * MySQL-compatible indexes (without PostgreSQL-specific features).
     */
    private function addMySQLIndexes(string $tableName): void
    {
        // Standard composite index for lookups
        DB::statement("
            CREATE INDEX idx_carts_lookup_covering
            ON `{$tableName}` (identifier, instance, id, version, updated_at, expires_at)
        ");

        // Index for expired cart cleanup
        DB::statement("
            CREATE INDEX idx_carts_expired
            ON `{$tableName}` (expires_at)
        ");

        // Index for analytics queries
        DB::statement("
            CREATE INDEX idx_carts_analytics
            ON `{$tableName}` (updated_at, instance)
        ");
    }
};
