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

        Schema::create(config('events.database.tables.event_management_assignments', 'event_management_assignments'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('manageable_type')->index();
            $table->uuid('manageable_id')->index();
            $table->string('manager_type')->index();
            $table->uuid('manager_id')->index();
            $table->string('assigned_by_type')->nullable()->index();
            $table->uuid('assigned_by_id')->nullable()->index();
            $table->string('role')->index();
            $table->{$jsonType}('permissions')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
