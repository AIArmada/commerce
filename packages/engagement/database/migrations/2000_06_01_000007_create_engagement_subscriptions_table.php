<?php

/**
 * Creates the engagement_subscriptions table.
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
        $tableName = (string) config('engagement.database.tables.subscriptions', 'subscriptions');
        $driver = ConnectionDriver::name(Schema::getConnection());

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf('Engagement subscriptions identity migration cannot run on unsupported database driver [%s].', $driver));
        }

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('subscriber_type')->index();
            $table->uuid('subscriber_id')->index();
            $table->index(['subscriber_type', 'subscriber_id']);
            $table->string('subscribable_type')->nullable()->index();
            $table->uuid('subscribable_id')->nullable()->index();
            $table->string('subscription_type')->index();
            $table->string('status')->index();
            $table->{$jsonType}('criteria')->nullable();
            $table->string('criteria_hash', 64)->index();
            $table->string('notification_level')->nullable()->index();
            $table->{$jsonType}('notification_preferences')->nullable();
            $table->timestampTz('subscribed_at')->nullable()->index();
            $table->timestampTz('muted_at')->nullable();
            $table->timestampTz('unsubscribed_at')->nullable()->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->string('source')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestampsTz();
            $table->index(['status', 'subscribable_type', 'subscribable_id'], 'engagement_subscriptions_match_idx');
            $table->unique(['subscriber_type', 'subscriber_id', 'subscribable_type', 'subscribable_id', 'subscription_type', 'criteria_hash', 'owner_type', 'owner_id'], 'engagement_subscriptions_identity_unique');
        });

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (subscriber_type, subscriber_id, subscription_type, criteria_hash, subscribable_type, subscribable_id) WHERE owner_type IS NULL AND owner_id IS NULL AND subscribable_type IS NOT NULL AND subscribable_id IS NOT NULL',
            $grammar->wrap('engagement_subscriptions_global_subject_unique'),
            $grammar->wrapTable($tableName),
        ));

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (subscriber_type, subscriber_id, subscription_type, criteria_hash) WHERE owner_type IS NULL AND owner_id IS NULL AND subscribable_type IS NULL AND subscribable_id IS NULL',
            $grammar->wrap('engagement_subscriptions_global_unbound_unique'),
            $grammar->wrapTable($tableName),
        ));

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (subscriber_type, subscriber_id, subscription_type, criteria_hash, owner_type, owner_id) WHERE owner_type IS NOT NULL AND owner_id IS NOT NULL AND subscribable_type IS NULL AND subscribable_id IS NULL',
            $grammar->wrap('engagement_subscriptions_owner_unbound_unique'),
            $grammar->wrapTable($tableName),
        ));
    }
};
