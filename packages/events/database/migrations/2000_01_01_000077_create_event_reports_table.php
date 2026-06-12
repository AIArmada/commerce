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

        Schema::create(config('events.database.tables.event_reports', 'event_reports'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('reportable_type')->index();
            $table->string('reportable_id')->index();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('reporter_type')->nullable()->index();
            $table->string('reporter_id')->nullable()->index();
            $table->string('report_type')->index();
            $table->string('status')->index();
            $table->string('severity')->index();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('reviewed_by_type')->nullable()->index();
            $table->string('reviewed_by_id')->nullable()->index();
            $table->timestampTz('reported_at');
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->text('resolution')->nullable();
            $table->text('internal_notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
