<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $tableName = config('cart.database.table', 'carts');
        $jsonType = (string) commerce_json_column_type('cart', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('identifier')->index();
            $table->string('owner_scope')->default('global');
            $table->nullableUuidMorphs('owner');
            $table->string('instance')->default('default')->index();
            $table->{$jsonType}('items')->nullable();
            $table->{$jsonType}('conditions')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->integer('version')->default(1)->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('checked_out_at')->nullable();
            $table->timestampTz('abandoned_at')->nullable();
            $table->uuid('merged_into_id')->nullable();
            $table->timestampsTz();

            $table->unique(['owner_scope', 'identifier', 'instance']);
            $table->index('expired_at');
            $table->index('checked_out_at');
            $table->index('abandoned_at');
            $table->index('merged_into_id');
        });

        $driver = ConnectionDriver::name(Schema::getConnection());

        if ($jsonType === 'jsonb' && $driver === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS carts_items_gin_index ON \"{$tableName}\" USING GIN (\"items\")");
            DB::statement("CREATE INDEX IF NOT EXISTS carts_conditions_gin_index ON \"{$tableName}\" USING GIN (\"conditions\")");
            DB::statement("CREATE INDEX IF NOT EXISTS carts_metadata_gin_index ON \"{$tableName}\" USING GIN (\"metadata\")");
        }

        if ($driver === 'pgsql') {
            $this->addPostgreSQLIndexes($tableName, $jsonType);
        } else {
            $this->addMySQLIndexes($tableName);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('cart.database.table', 'carts'));
    }

    private function addPostgreSQLIndexes(string $tableName, string $jsonType): void
    {
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_lookup_covering
            ON \"{$tableName}\" (owner_type, owner_id, identifier, instance)
            INCLUDE (id, version, updated_at, expires_at)
        ");

        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_active
            ON \"{$tableName}\" (owner_type, owner_id, expires_at, identifier, instance)
        ");

        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_expired
            ON \"{$tableName}\" (owner_type, owner_id, expires_at)
            WHERE expires_at IS NOT NULL
        ");

        if ($jsonType === 'jsonb') {
            DB::statement("
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_analytics
                ON \"{$tableName}\" (owner_type, owner_id, updated_at, instance)
                WHERE items IS NOT NULL AND items != '[]'::jsonb
            ");
        } else {
            DB::statement("
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_analytics
                ON \"{$tableName}\" (owner_type, owner_id, updated_at, instance)
                WHERE items IS NOT NULL
            ");
        }
    }

    private function addMySQLIndexes(string $tableName): void
    {
        DB::statement("
            CREATE INDEX idx_carts_lookup_covering
            ON `{$tableName}` (owner_type, owner_id, identifier, instance, id, version, updated_at, expires_at)
        ");

        DB::statement("
            CREATE INDEX idx_carts_expired
            ON `{$tableName}` (owner_type, owner_id, expires_at)
        ");

        DB::statement("
            CREATE INDEX idx_carts_analytics
            ON `{$tableName}` (owner_type, owner_id, updated_at, instance)
        ");
    }
};
