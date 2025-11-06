<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::create($tablePrefix.'webhooks', function (Blueprint $table): void {
            // Core API fields - Webhook object structure from CHIP API
            $table->uuid('id')->primary();
            $table->string('type')->default('webhook');
            $table->integer('created_on'); // Unix timestamp as per API
            $table->integer('updated_on'); // Unix timestamp as per API

            // Webhook configuration - as per CHIP API
            $table->string('title', 100); // Arbitrary title of webhook
            $jsonType = (string) commerce_json_column_type('chip', 'json');
            $table->{$jsonType}('events'); // List of events to trigger webhook
            $table->string('callback', 500); // Callback URL
            $table->boolean('all_events')->default(false); // Trigger on all events
            $table->text('public_key')->nullable(); // PEM-encoded RSA public key

            // Event processing fields - for handling incoming webhooks
            $table->string('event_type')->nullable(); // Which event triggered webhook
            $table->{$jsonType}('payload')->nullable(); // Full webhook payload
            $table->{$jsonType}('headers')->nullable(); // Request headers
            $table->text('signature')->nullable(); // Webhook signature

            // Processing status
            $table->boolean('verified')->default(false);
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->integer('processing_attempts')->default(0);

            // Laravel timestamps for internal use
            $table->timestamps();

            // Indexes for optimal query performance
            $table->index(['event_type', 'processed']);
            $table->index(['verified', 'processed']);
            $table->index('created_on');
            $table->index('callback');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::dropIfExists($tablePrefix.'webhooks');
    }
};
