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

        Schema::create(config('communications.database.tables.events', 'communication_events'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->foreignUuid('communication_id');
            $table->foreignUuid('delivery_id')->nullable();
            $table->foreignUuid('attempt_id')->nullable();
            $table->string('event');
            $table->string('source');
            $table->string('provider')->nullable();
            $table->string('provider_event_id')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('received_at');
            $table->timestampTz('signature_validated_at')->nullable();
            $table->timestampTz('processed_at')->nullable()->index();
            $table->timestampTz('ignored_at')->nullable();
            $table->timestampTz('failed_at')->nullable()->index();
            $table->{$jsonType}('payload')->nullable();
            $table->text('failure_message')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['delivery_id', 'occurred_at']);
            $table->index(['communication_id', 'occurred_at']);
            $table->index(['provider', 'provider_event_id']);
            $table->index(['provider', 'provider_message_id']);
        });
    }
};
