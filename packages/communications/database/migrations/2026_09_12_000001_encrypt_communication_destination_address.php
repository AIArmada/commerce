<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('communications.database.tables.destinations', 'communication_destinations');

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'address')) {
            return;
        }

        if (Schema::getColumnType($tableName, 'address') === 'text') {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->text('address')->nullable()->change();
        });
    }
};
