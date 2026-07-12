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

        Schema::create(config('events.database.tables.event_occurrences', 'event_occurrences'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->timestampTz('starts_at')->nullable()->index();
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone')->nullable();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->string('delivery_mode')->nullable()->index();
            $table->integer('capacity')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('delayed_at')->nullable();
            $table->timestampTz('postponed_at')->nullable();
            $table->timestampTz('rescheduled_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->uuid('rescheduled_from_occurrence_id')->nullable()->index();
            $table->uuid('rescheduled_to_occurrence_id')->nullable()->index();
            $table->text('status_reason')->nullable();
            $table->text('status_message')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->string('pricing_mode')->nullable()->index();
            $table->string('registration_mode')->nullable()->index();
            $table->boolean('issue_passes_for_free')->default(true);
        });
    }
};
