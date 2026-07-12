<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('events', 'jsonb');

        Schema::create(config('events.database.tables.event_search_documents', 'event_search_documents'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('searchable_type')->index();
            $table->string('searchable_id')->index();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('document_type')->index();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->text('body')->nullable();
            $table->{$jsonType}('keywords')->nullable();
            $table->{$jsonType}('facets')->nullable();
            $table->{$jsonType}('coordinates')->nullable();
            $table->timestampTz('indexed_at')->nullable();
            $table->timestampTz('stale_at')->nullable();
            $table->string('status')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql' && commerce_json_column_type('events', 'jsonb') === 'jsonb') {
            $searchTable = config('events.database.tables.event_search_documents', 'event_search_documents');
            DB::statement("CREATE INDEX IF NOT EXISTS {$searchTable}_facets_gin_idx ON {$searchTable} USING GIN (facets jsonb_path_ops)");
            DB::statement("CREATE INDEX IF NOT EXISTS {$searchTable}_facets_gin_default_idx ON {$searchTable} USING GIN (facets)");
        }
    }
};
