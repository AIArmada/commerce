<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('events.database.tables.event_sessions', 'event_sessions');

        if (! Schema::hasColumn($tableName, 'rescheduled_at')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestampTz('rescheduled_at')->nullable()->index();
            });
        }
    }
};
