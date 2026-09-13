<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('communications', 'jsonb');

        Schema::create(config('communications.database.tables.deliveries', 'communication_deliveries'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->foreignUuid('communication_id')->index();
            $table->foreignUuid('recipient_id');
            $table->foreignUuid('content_id')->nullable();
            $table->string('channel');
            $table->string('provider')->nullable();
            $table->string('provider_account_key')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('status')->index();
            $table->text('destination_ciphertext')->nullable();
            $table->string('destination_hash')->nullable();
            $table->string('destination_hint')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->integer('max_attempts')->nullable();
            $table->integer('cost_minor')->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('sending_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('clicked_at')->nullable();
            $table->timestampTz('replied_at')->nullable();
            $table->timestampTz('bounced_at')->nullable();
            $table->timestampTz('complained_at')->nullable();
            $table->timestampTz('unsubscribed_at')->nullable();
            $table->timestampTz('failed_at')->nullable()->index();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('suppressed_at')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id', 'status', 'created_at']);
            $table->index(['communication_id', 'status']);
            $table->index(['recipient_id', 'channel']);
            $table->index(['provider', 'provider_message_id']);
            $table->index(['destination_hash', 'channel']);
            $table->index(['scheduled_at']);
        });
    }
};
