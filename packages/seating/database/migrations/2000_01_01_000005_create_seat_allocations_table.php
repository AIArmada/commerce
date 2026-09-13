<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('seating', 'json');
        $tableName = (string) config('seating.database.tables.seat_allocations', 'seat_allocations');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->uuid('seat_id')->nullable();
            $table->uuid('seat_section_id')->nullable()->index();
            $table->nullableMorphs('allocated_to');
            $table->string('reference')->nullable()->index();
            $table->timestampTz('allocated_at');
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->string('released_by_type')->nullable();
            $table->string('released_by_id')->nullable();
            $table->string('status')->default('active')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['seat_id', 'status']);
            $table->index(['seat_section_id', 'status'], 'sa_section_status_idx');
            $table->index(['released_by_type', 'released_by_id'], 'sa_released_by_idx');
        });

        $connection = Schema::getConnection();

        if (! in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            /*
             * Drivers without partial-index predicates retain the existing
             * composite (seat_id, status) lookup index. Conversion locks the
             * seat row with FOR UPDATE as the integrity guard on those drivers.
             */
            return;
        }

        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s = \'active\'',
            $grammar->wrap('seating_allocations_active_seat_unique'),
            $grammar->wrapTable($tableName),
            $grammar->wrap('seat_id'),
            $grammar->wrap('status'),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seating.database.tables.seat_allocations', 'seat_allocations'));
    }
};
