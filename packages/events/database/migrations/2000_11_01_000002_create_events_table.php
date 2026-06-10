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

        Schema::create(config('events.database.tables.events', 'events'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->uuid('event_series_id')->nullable()->index();
            $table->uuid('parent_event_id')->nullable()->index();
            $table->uuid('product_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->string('status', 32)->default('draft')->index();
            $table->string('structure', 32)->default('standalone')->index();
            $table->string('default_timezone', 64)->nullable();
            $table->unsignedInteger('default_duration_minutes')->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id', 'status']);
            $table->index(['event_series_id', 'status']);
            $table->index(['event_series_id', 'parent_event_id']);
            $table->index(['parent_event_id', 'structure']);
            $table->unique(['owner_type', 'owner_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('events.database.tables.events', 'events'));
    }
};
