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

        Schema::create(config('events.database.tables.event_submissions', 'event_submissions'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('submitter_type')->nullable()->index();
            $table->uuid('submitter_id')->nullable()->index();
            $table->string('target_type')->nullable()->index();
            $table->uuid('target_id')->nullable()->index();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->{$jsonType}('submission_data')->nullable();
            $table->string('status')->index();
            $table->timestampTz('submitted_at');
            $table->timestampTz('reviewed_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
