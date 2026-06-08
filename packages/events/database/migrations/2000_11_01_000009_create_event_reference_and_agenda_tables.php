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
        $tables = (array) ($databaseConfig['tables'] ?? []);
        $jsonColumnType = (string) ($databaseConfig['json_column_type'] ?? commerce_json_column_type('events', 'jsonb'));

        $referencesTable = (string) ($tables['references'] ?? 'event_reference_assignments');
        $agendaItemsTable = (string) ($tables['agenda_items'] ?? 'event_agenda_items');

        Schema::create($referencesTable, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->nullableMorphs('assignable');
            $table->nullableMorphs('reference');
            $table->string('reference_kind');
            $table->string('display_label')->nullable();
            $table->string('source_label')->nullable();
            $table->text('url')->nullable();
            $table->unsignedInteger('order_column')->nullable();
            $table->{$jsonColumnType}('metadata')->nullable();
            $table->timestamps();

            $table->index(['assignable_type', 'assignable_id', 'reference_kind'], 'event_reference_assignments_assignable_kind_index');
            $table->index(['reference_kind', 'source_label'], 'event_reference_assignments_kind_source_index');
            $table->index(['reference_type', 'reference_id'], 'event_reference_assignments_reference_index');
        });

        Schema::create($agendaItemsTable, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->foreignUuid('occurrence_id');
            $table->string('segment_key');
            $table->string('segment_type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('order_column')->nullable();
            $table->{$jsonColumnType}('metadata')->nullable();
            $table->timestamps();

            $table->index(['occurrence_id', 'segment_key'], 'event_agenda_items_occurrence_segment_index');
            $table->index(['occurrence_id', 'order_column'], 'event_agenda_items_occurrence_order_index');
        });
    }
};
