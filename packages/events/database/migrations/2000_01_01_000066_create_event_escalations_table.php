<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('events.database.tables.event_escalations', 'event_escalations'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_id')->index();
            $table->string('type');
            $table->string('decision_key')->unique();
            $table->text('reason')->nullable();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['event_id', 'type'], 'event_escalations_event_type_index');
        });
    }
};
