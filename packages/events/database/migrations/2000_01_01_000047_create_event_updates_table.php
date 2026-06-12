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

        Schema::create(config('events.database.tables.event_updates', 'event_updates'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->uuid('event_change_log_id')->nullable()->index();
            $table->string('update_type')->index();
            $table->string('title');
            $table->text('message');
            $table->string('severity')->index();
            $table->string('visibility')->index();
            $table->boolean('is_pinned')->default(false);
            $table->timestampTz('starts_showing_at')->nullable();
            $table->timestampTz('stops_showing_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->string('created_by_type')->nullable()->index();
            $table->uuid('created_by_id')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
