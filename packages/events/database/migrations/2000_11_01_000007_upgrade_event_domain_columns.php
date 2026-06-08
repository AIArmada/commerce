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

        $eventsTable = (string) config('events.database.tables.events', 'events');
        $venuesTable = (string) config('events.database.tables.venues', 'event_venues');
        $peopleTable = (string) config('events.database.tables.people', 'event_speakers');

        $this->upgradeEventsTable($eventsTable, $jsonType);
        $this->upgradeVenuesTable($venuesTable);
        $this->upgradeEventPeopleTable($peopleTable, $jsonType);
    }

    private function upgradeEventsTable(string $tableName, string $jsonType): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($tableName, $jsonType): void {
            $hasOrganizerType = Schema::hasColumn($tableName, 'organizer_type');
            $hasOrganizerId = Schema::hasColumn($tableName, 'organizer_id');

            if (! $hasOrganizerType && ! $hasOrganizerId) {
                $table->nullableUuidMorphs('organizer');
            } else {
                if (! $hasOrganizerType) {
                    $table->string('organizer_type')->nullable()->after('owner_id');
                }

                if (! $hasOrganizerId) {
                    $table->uuid('organizer_id')->nullable()->after('organizer_type');
                }
            }

            if (! Schema::hasColumn($tableName, 'moderation_status')) {
                $table->string('moderation_status', 32)->default('approved')->index()->after('status');
            }

            if (! Schema::hasColumn($tableName, 'structure')) {
                $table->string('structure', 32)->default('standalone')->index()->after('moderation_status');
            }

            if (! Schema::hasColumn($tableName, 'parent_event_id')) {
                $table->uuid('parent_event_id')->nullable()->index()->after('event_series_id');
            }

            if (! Schema::hasColumn($tableName, 'visibility')) {
                $table->string('visibility', 32)->default('public')->index()->after('moderation_status');
            }

            if (! Schema::hasColumn($tableName, 'published_at')) {
                $table->timestampTz('published_at')->nullable()->index()->after('default_duration_minutes');
            }

            if (! Schema::hasColumn($tableName, 'public_starts_at')) {
                $table->timestampTz('public_starts_at')->nullable()->index()->after('published_at');
            }

            if (! Schema::hasColumn($tableName, 'public_ends_at')) {
                $table->timestampTz('public_ends_at')->nullable()->index()->after('public_starts_at');
            }

            if (! Schema::hasColumn($tableName, 'media_references')) {
                $table->{$jsonType}('media_references')->nullable()->after('description');
            }

            if (! Schema::hasColumn($tableName, 'taxonomy')) {
                $table->{$jsonType}('taxonomy')->nullable()->after('media_references');
            }

            if (! Schema::hasColumn($tableName, 'search_keywords')) {
                $table->text('search_keywords')->nullable()->after('taxonomy');
            }
        });
    }

    private function upgradeVenuesTable(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'location_type')) {
                $table->string('location_type', 32)->default('physical')->index()->after('slug');
            }

            if (! Schema::hasColumn($tableName, 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('country');
            }

            if (! Schema::hasColumn($tableName, 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }

            if (! Schema::hasColumn($tableName, 'map_url')) {
                $table->text('map_url')->nullable()->after('longitude');
            }

            if (! Schema::hasColumn($tableName, 'external_id')) {
                $table->string('external_id')->nullable()->index()->after('map_url');
            }
        });
    }

    private function upgradeEventPeopleTable(string $tableName, string $jsonType): void
    {
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
                $table->uuid('id')->primary();
                $table->nullableUuidMorphs('owner');
                $table->uuid('event_id')->index();
                $table->nullableUuidMorphs('person');
                $table->string('display_name')->nullable();
                $table->string('role')->nullable();
                $table->string('role_key')->nullable();
                $table->string('role_label')->nullable();
                $table->boolean('is_public')->default(true)->index();
                $table->text('biography')->nullable();
                $table->unsignedInteger('order_column')->nullable();
                $table->{$jsonType}('metadata')->nullable();
                $table->timestampsTz();

                $table->index(['event_id', 'order_column']);
                $table->index(['event_id', 'role_key']);
            });

            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'person_type')) {
                $table->string('person_type')->nullable()->after('event_id');
            }

            if (! Schema::hasColumn($tableName, 'person_id')) {
                $table->uuid('person_id')->nullable()->after('person_type');
            }

            if (! Schema::hasColumn($tableName, 'role_key')) {
                $table->string('role_key')->nullable()->after('role');
            }

            if (! Schema::hasColumn($tableName, 'role_label')) {
                $table->string('role_label')->nullable()->after('role_key');
            }

            if (! Schema::hasColumn($tableName, 'is_public')) {
                $table->boolean('is_public')->default(true)->index()->after('role_label');
            }

            if (! Schema::hasColumn($tableName, 'order_column')) {
                $table->unsignedInteger('order_column')->nullable()->after('biography');
            }
        });
    }
};
