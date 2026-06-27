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

        Schema::create(config('events.database.tables.event_registrations', 'event_registrations'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('registrant_type')->nullable()->index();
            $table->uuid('registrant_id')->nullable()->index();
            $table->index(['registrant_type', 'registrant_id']);
            $table->string('registration_no')->unique();
            $table->string('registration_type')->index();
            $table->string('status')->index();
            $table->string('source')->index();
            $table->integer('total_participants')->default(1);
            $table->bigInteger('total_amount')->nullable();
            $table->string('currency')->nullable();
            $table->string('external_order_id')->nullable()->index();
            $table->string('external_order_type')->nullable()->index();
            $table->string('payment_status')->nullable()->index();
            $table->timestampTz('registered_at')->index();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('waitlisted_at')->nullable();
            $table->timestampTz('refunded_at')->nullable()->index();
            $table->timestampTz('expired_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->text('notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->string('parent_registration_id', 36)->nullable()->index();
            $table->boolean('is_bundle_root')->default(false)->index();
            $table->{$jsonType}('pass_entitlements')->nullable();
        });
    }
};
