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

        Schema::create(config('events.database.tables.event_sessions', 'event_sessions'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->index();
            $table->string('title');
            $table->string('slug')->nullable()->index();
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->timestampTz('starts_at')->nullable()->index();
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone')->nullable();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->string('delivery_mode')->nullable()->index();
            $table->integer('capacity')->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('delayed_at')->nullable();
            $table->timestampTz('postponed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->text('status_message')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
