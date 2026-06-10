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

        Schema::create(config('events.database.tables.venues', 'event_venues'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('slug');
            $table->string('status', 32)->default('active');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country', 2)->default('MY');
            $table->string('timezone', 64)->nullable();
            $table->text('notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id', 'country']);
            $table->unique(['owner_type', 'owner_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('events.database.tables.venues', 'event_venues'));
    }
};
