<?php

/**
 * Creates the engagement_reminders table.
 * Part of the aiarmada/engagement package.
 *
 * @see https://github.com/aiarmada/engagement/docs
 */
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('engagement.database.json_column_type', 'jsonb');
        Schema::create(config('engagement.database.tables.reminders', 'reminders'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('remindable_type')->index();
            $table->uuid('remindable_id')->index();
            $table->index(['remindable_type', 'remindable_id']);
            $table->string('recipient_type')->index();
            $table->uuid('recipient_id')->index();
            $table->string('reminder_type')->index();
            $table->string('status')->index();
            $table->timestampTz('remind_at')->nullable()->index();
            $table->integer('offset_minutes')->nullable();
            $table->string('anchor_type')->nullable()->index();
            $table->string('anchor_code')->nullable()->index();
            $table->string('channel')->nullable()->index();
            $table->string('notification_class')->nullable();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('sent_at')->nullable()->index();
            $table->timestampTz('cancelled_at')->nullable()->index();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableMorphs('owner');
            $table->timestampsTz();
        });
    }
};
