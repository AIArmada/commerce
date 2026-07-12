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

        Schema::create(config('events.database.tables.event_involvements', 'event_involvements'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->index(['event_id', 'event_occurrence_id', 'event_session_id']);
            $table->string('involveable_type')->nullable()->index();
            $table->uuid('involveable_id')->nullable()->index();
            $table->index(['involveable_type', 'involveable_id']);
            $table->uuid('event_role_id')->nullable()->index();
            $table->string('role_code')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->string('visibility')->nullable()->index();
            $table->string('prominence')->nullable()->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->uuid('replaced_by_involvement_id')->nullable()->index();
            $table->text('replacement_reason')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
