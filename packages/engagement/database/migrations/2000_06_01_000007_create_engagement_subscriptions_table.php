<?php

/**
 * Creates the engagement_subscriptions table.
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
        Schema::create(config('engagement.database.tables.subscriptions', 'subscriptions'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('subscriber_type')->index();
            $table->uuid('subscriber_id')->index();
            $table->index(['subscriber_type', 'subscriber_id']);
            $table->string('subscribable_type')->nullable()->index();
            $table->uuid('subscribable_id')->nullable()->index();
            $table->string('subscription_type')->index();
            $table->string('status')->index();
            $table->{$jsonType}('criteria')->nullable();
            $table->string('notification_level')->nullable()->index();
            $table->{$jsonType}('notification_preferences')->nullable();
            $table->timestampTz('subscribed_at')->nullable()->index();
            $table->timestampTz('muted_at')->nullable();
            $table->timestampTz('unsubscribed_at')->nullable()->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->string('source')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableMorphs('owner');
            $table->timestampsTz();
        });
    }
};
