<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('events', 'jsonb');

        Schema::create(config('events.database.tables.event_revisions', 'event_revisions'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('revisable_type')->index();
            $table->string('revisable_id')->index();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->integer('version_no');
            $table->string('revision_type')->index();
            $table->string('status')->index();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->{$jsonType}('payload');
            $table->{$jsonType}('diff')->nullable();
            $table->string('submitted_by_type')->nullable()->index();
            $table->uuid('submitted_by_id')->nullable()->index();
            $table->string('reviewed_by_type')->nullable()->index();
            $table->uuid('reviewed_by_id')->nullable()->index();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
