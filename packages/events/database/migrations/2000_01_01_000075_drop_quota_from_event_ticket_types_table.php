<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('events.database.tables.event_ticket_types', 'event_ticket_types');

        if (Schema::hasColumn($tableName, 'quota')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('quota');
            });
        }
    }
};
