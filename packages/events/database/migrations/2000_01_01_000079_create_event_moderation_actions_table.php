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

        Schema::create(config('events.database.tables.event_moderation_actions', 'event_moderation_actions'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('event_report_id')->nullable()->index();
            $table->string('actionable_type')->index();
            $table->string('actionable_id')->index();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('action_type')->index();
            $table->string('status')->index();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('performed_by_type')->nullable()->index();
            $table->string('performed_by_id')->nullable()->index();
            $table->timestampTz('performed_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
