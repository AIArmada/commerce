<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('communications.database.json_column_type', 'jsonb');

        Schema::create(config('communications.database.tables.communication_attempts', 'communication_attempts'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->foreignUuid('delivery_id');
            $table->integer('attempt_number');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->{$jsonType}('request_payload')->nullable();
            $table->{$jsonType}('response_payload')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampTz('failed_at')->nullable()->index();
            $table->integer('duration_ms')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['delivery_id', 'attempt_number']);
            $table->index(['provider', 'provider_message_id']);
        });
    }
};
