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

        Schema::create(config('events.database.tables.event_verifications', 'event_verifications'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('verifiable_type')->index();
            $table->string('verifiable_id')->index();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('verification_type')->index();
            $table->string('status')->index();
            $table->string('confidence_level')->nullable()->index();
            $table->string('source_type')->nullable()->index();
            $table->string('source_id')->nullable()->index();
            $table->string('source_label')->nullable();
            $table->text('source_url')->nullable();
            $table->string('verified_by_type')->nullable()->index();
            $table->string('verified_by_id')->nullable()->index();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
