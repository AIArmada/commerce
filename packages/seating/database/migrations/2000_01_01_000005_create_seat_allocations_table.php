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

        commerce_schema_create_if_missing(config('seating.database.tables.seat_allocations', 'seat_allocations'), function (Blueprint $table) use ($jsonType): void {
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
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seating.database.tables.seat_allocations', 'seat_allocations'));
    }
};
