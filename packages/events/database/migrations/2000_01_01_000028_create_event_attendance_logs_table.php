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

        Schema::create(config('events.database.tables.event_attendance_logs', 'event_attendance_logs'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_attendance_id')->index();
            $table->string('action')->index();
            $table->string('source')->nullable()->index();
            $table->string('performed_by_type')->nullable()->index();
            $table->uuid('performed_by_id')->nullable()->index();
            $table->timestampTz('occurred_at')->index();
            $table->text('notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }
};
