<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('events.database.tables.event_submissions', 'event_submissions');

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'review_reason')) {
                $table->text('review_reason')->nullable();
            }

            if (! Schema::hasColumn($tableName, 'review_notes')) {
                $table->text('review_notes')->nullable();
            }
        });
    }
};
