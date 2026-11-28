<?php

/**
 * Creates the engagement_reminders table.
 * Part of the aiarmada/engagement package.
 *
 * @see https://github.com/aiarmada/engagement/docs
 */
declare(strict_types=1);
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('engagement', 'jsonb');
        $tableName = (string) config('engagement.database.tables.reminders', 'reminders');
        $schema = Schema::connection($this->getConnection());
        $connection = $schema->getConnection();
        $driver = ConnectionDriver::name($connection);

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf('Engagement reminder identity migration cannot run on unsupported database driver [%s].', $driver));
        }

        $schema->create($tableName, function (Blueprint $table) use ($jsonType): void {
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
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('claim_expires_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->nullableUuidMorphs('owner');
            $table->timestampsTz();
            $table->index(['status', 'remind_at'], 'engagement_reminders_status_remind_at_idx');
            $table->index(['status', 'claim_expires_at'], 'engagement_reminders_status_claim_expires_at_idx');
        });

        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            "CREATE UNIQUE INDEX %s ON %s (recipient_type, recipient_id, remindable_type, remindable_id, reminder_type, remind_at) WHERE status IN ('pending', 'scheduled') AND remind_at IS NOT NULL AND owner_type IS NULL AND owner_id IS NULL AND channel IS NULL",
            $grammar->wrap('engagement_reminders_global_default_unique'),
            $grammar->wrapTable($tableName),
        ));

        $connection->statement(sprintf(
            "CREATE UNIQUE INDEX %s ON %s (recipient_type, recipient_id, remindable_type, remindable_id, reminder_type, remind_at, channel) WHERE status IN ('pending', 'scheduled') AND remind_at IS NOT NULL AND owner_type IS NULL AND owner_id IS NULL AND channel IS NOT NULL",
            $grammar->wrap('engagement_reminders_global_channel_unique'),
            $grammar->wrapTable($tableName),
        ));

        $connection->statement(sprintf(
            "CREATE UNIQUE INDEX %s ON %s (recipient_type, recipient_id, remindable_type, remindable_id, reminder_type, remind_at, owner_type, owner_id) WHERE status IN ('pending', 'scheduled') AND remind_at IS NOT NULL AND owner_type IS NOT NULL AND owner_id IS NOT NULL AND channel IS NULL",
            $grammar->wrap('engagement_reminders_owner_default_unique'),
            $grammar->wrapTable($tableName),
        ));

        $connection->statement(sprintf(
            "CREATE UNIQUE INDEX %s ON %s (recipient_type, recipient_id, remindable_type, remindable_id, reminder_type, remind_at, owner_type, owner_id, channel) WHERE status IN ('pending', 'scheduled') AND remind_at IS NOT NULL AND owner_type IS NOT NULL AND owner_id IS NOT NULL AND channel IS NOT NULL",
            $grammar->wrap('engagement_reminders_owner_channel_unique'),
            $grammar->wrapTable($tableName),
        ));
    }
};
