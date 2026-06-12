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

        Schema::create(config('events.database.tables.event_change_logs', 'event_change_logs'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('subject_type')->index();
            $table->uuid('subject_id')->nullable()->index();
            $table->index(['subject_type', 'subject_id']);
            $table->string('change_type')->index();
            $table->string('change_category')->index();
            $table->{$jsonType}('old_value')->nullable();
            $table->{$jsonType}('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('impact_level')->index();
            $table->string('visibility')->index();
            $table->boolean('requires_notification')->default(false);
            $table->string('changed_by_type')->nullable()->index();
            $table->uuid('changed_by_id')->nullable()->index();
            $table->index(['changed_by_type', 'changed_by_id']);
            $table->timestampTz('changed_at')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }
};
