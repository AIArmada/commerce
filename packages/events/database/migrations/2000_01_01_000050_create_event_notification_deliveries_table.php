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

        Schema::create(config('events.database.tables.event_notification_deliveries', 'event_notification_deliveries'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_notification_batch_id')->index();
            $table->string('recipient_type')->index();
            $table->uuid('recipient_id')->index();
            $table->string('channel');
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestampTz('leased_at')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('dead_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['event_notification_batch_id', 'recipient_type', 'recipient_id', 'channel'], 'event_notification_delivery_unique');
        });
    }
};
