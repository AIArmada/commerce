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

        Schema::create(config('events.database.tables.event_seat_holds', 'event_seat_holds'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->uuid('event_seat_id')->nullable()->index();
            $table->uuid('event_seat_section_id')->nullable()->index();
            $table->string('holder_type')->nullable()->index();
            $table->uuid('holder_id')->nullable()->index();
            $table->uuid('event_registration_id')->nullable()->index();
            $table->integer('quantity')->default(1);
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('converted_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
