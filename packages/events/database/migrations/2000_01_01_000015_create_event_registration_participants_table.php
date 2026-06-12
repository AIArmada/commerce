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

        Schema::create(config('events.database.tables.event_registration_participants', 'event_registration_participants'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_registration_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('participant_type')->nullable()->index();
            $table->uuid('participant_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('relationship_to_registrant')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->integer('age')->nullable();
            $table->string('gender')->nullable()->index();
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
