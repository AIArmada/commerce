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

        Schema::create(config('events.database.tables.event_notification_batches', 'event_notification_batches'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->uuid('event_update_id')->nullable()->index();
            $table->uuid('event_change_log_id')->nullable()->index();
            $table->string('audience_scope')->index();
            $table->string('title');
            $table->text('message')->nullable();
            $table->{$jsonType}('channels')->nullable();
            $table->string('status')->index();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('created_by_type')->nullable()->index();
            $table->uuid('created_by_id')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
