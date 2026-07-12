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

        Schema::create(config('events.database.tables.event_access_policies', 'event_access_policies'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->boolean('registration_required')->default(false)->index();
            $table->boolean('approval_required')->default(false)->index();
            $table->boolean('payment_required')->default(false)->index();
            $table->boolean('ticket_required')->default(false)->index();
            $table->boolean('seating_required')->default(false)->index();
            $table->boolean('walk_in_allowed')->default(true)->index();
            $table->integer('capacity')->nullable();
            $table->boolean('waitlist_enabled')->default(false)->index();
            $table->timestampTz('opens_at')->nullable();
            $table->timestampTz('closes_at')->nullable();
            $table->text('notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
