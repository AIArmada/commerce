<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $jsonType = config('events.database.json_column_type', 'jsonb');

        if ($jsonType !== 'jsonb') {
            return;
        }

        $eventsTable = config('events.database.tables.events', 'events');
        $searchTable = config('events.database.tables.event_search_documents', 'event_search_documents');

        DB::statement(
            "CREATE INDEX IF NOT EXISTS {$eventsTable}_metadata_gin_idx ON {$eventsTable} USING GIN (metadata jsonb_path_ops)"
        );

        DB::statement(
            "CREATE INDEX IF NOT EXISTS {$searchTable}_facets_gin_idx ON {$searchTable} USING GIN (facets jsonb_path_ops)"
        );

        DB::statement(
            "CREATE INDEX IF NOT EXISTS {$searchTable}_facets_gin_default_idx ON {$searchTable} USING GIN (facets)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $jsonType = config('events.database.json_column_type', 'jsonb');

        if ($jsonType !== 'jsonb') {
            return;
        }

        $eventsTable = config('events.database.tables.events', 'events');
        $searchTable = config('events.database.tables.event_search_documents', 'event_search_documents');

        DB::statement("DROP INDEX IF EXISTS {$eventsTable}_metadata_gin_idx");
        DB::statement("DROP INDEX IF EXISTS {$searchTable}_facets_gin_idx");
        DB::statement("DROP INDEX IF EXISTS {$searchTable}_facets_gin_default_idx");
    }
};
