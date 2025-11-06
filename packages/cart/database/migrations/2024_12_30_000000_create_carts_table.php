<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('cart.database.table', 'carts'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('identifier')->index();
            $table->string('instance')->default('default')->index();
            $jsonType = (string) commerce_json_column_type('cart', 'json');
            $table->{$jsonType}('items')->nullable();
            $table->{$jsonType}('conditions')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->integer('version')->default(1)->index();
            $table->timestamps();

            $table->unique(['identifier', 'instance']);
        });

        // Optional: create GIN indexes when using jsonb on PostgreSQL
        $tableName = config('cart.database.table', 'carts');
        if (
            commerce_json_column_type('cart', 'json') === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            DB::statement("CREATE INDEX IF NOT EXISTS carts_items_gin_index ON \"{$tableName}\" USING GIN (\"items\")");
            DB::statement("CREATE INDEX IF NOT EXISTS carts_conditions_gin_index ON \"{$tableName}\" USING GIN (\"conditions\")");
            DB::statement("CREATE INDEX IF NOT EXISTS carts_metadata_gin_index ON \"{$tableName}\" USING GIN (\"metadata\")");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('cart.database.table', 'carts'));
    }
};
