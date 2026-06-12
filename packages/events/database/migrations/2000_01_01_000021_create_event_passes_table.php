<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('events.database.json_column_type', 'jsonb');

        Schema::create(config('events.database.tables.event_passes', 'event_passes'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->uuid('event_registration_id')->nullable()->index();
            $table->uuid('event_registration_participant_id')->nullable()->index();
            $table->uuid('event_registration_item_id')->nullable()->index();
            $table->uuid('event_ticket_type_id')->nullable()->index();
            $table->string('pass_no')->unique();
            $table->string('qr_code')->nullable()->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('status')->index();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
