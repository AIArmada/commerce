<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('events.database.tables.event_registrations', 'event_registrations');

        if (Schema::hasColumn($tableName, 'refunded_at')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('refunded_at')->nullable()->index();
        });
    }
};
