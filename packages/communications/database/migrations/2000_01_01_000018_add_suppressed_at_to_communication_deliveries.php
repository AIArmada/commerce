<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('communications.database.tables.deliveries', 'communication_deliveries');

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'suppressed_at')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('suppressed_at')->nullable();
        });
    }
};
