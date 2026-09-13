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

        Schema::create(config('events.database.tables.event_availability_blocks', 'event_availability_blocks'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('blockable_type')->index();
            $table->string('blockable_id')->index();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('block_type')->index();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('timezone');
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->string('created_by_type')->nullable()->index();
            $table->string('created_by_id')->nullable()->index();
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
