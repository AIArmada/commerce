<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('affiliates.database.tables.webhook_deliveries', 'affiliate_webhook_deliveries'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('event_type', 64);
            $table->string('destination_key', 64);
            $table->text('endpoint');
            $table->text('headers')->nullable();
            $table->text('body_json');
            $table->string('signature', 64)->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestampTz('available_at')->nullable();
            $table->timestampTz('leased_at')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('dead_at')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestampsTz();

            $table->unique(['event_id', 'destination_key'], 'affiliate_webhook_delivery_event_destination_unique');
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.webhook_deliveries', 'affiliate_webhook_deliveries'));
    }
};
