<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $databaseConfig = (array) config('events.database', []);
        $jsonType = (string) ($databaseConfig['json_column_type'] ?? commerce_json_column_type('events', 'jsonb'));

        Schema::create(config('events.database.tables.series', 'event_series'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id', 'status']);
            $table->unique(['owner_type', 'owner_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('events.database.tables.series', 'event_series'));
    }
};
