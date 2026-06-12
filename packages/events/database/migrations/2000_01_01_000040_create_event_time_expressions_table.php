<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('events.database.json_column_type', 'jsonb');

        Schema::create(config('events.database.tables.event_time_expressions', 'event_time_expressions'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('time_mode')->index();
            $table->string('anchor_type')->nullable()->index();
            $table->string('anchor_code')->nullable()->index();
            $table->string('relation')->nullable()->index();
            $table->integer('offset_minutes')->nullable();
            $table->string('display_label')->nullable();
            $table->string('resolver_class')->nullable();
            $table->{$jsonType}('resolver_context')->nullable();
            $table->timestampTz('resolved_starts_at')->nullable();
            $table->timestampTz('resolved_ends_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
