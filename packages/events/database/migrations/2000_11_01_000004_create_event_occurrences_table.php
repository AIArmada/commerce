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

        Schema::create(config('events.database.tables.occurrences', 'commerce_event_occurrences'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->uuid('event_id')->index();
            $table->uuid('venue_id')->nullable()->index();
            $table->uuid('product_id')->nullable()->index();
            $table->uuid('variant_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone', 64);
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->timestamp('check_in_opens_at')->nullable();
            $table->timestamp('check_in_closes_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'starts_at']);
            $table->index(['venue_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('events.database.tables.occurrences', 'commerce_event_occurrences'));
    }
};
