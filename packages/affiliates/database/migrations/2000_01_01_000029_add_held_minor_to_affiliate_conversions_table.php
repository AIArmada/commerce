<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.conversions', 'conversions');

        if (! Schema::hasColumn($tableName, 'held_minor')) {
            // Mirrors commission_minor's type exactly: held copies it.
            // No data update: pre-existing rows keep held 0 and are
            // credited in full on approval by the applied-amount
            // arithmetic in ApplyConversionAccounting.
            Schema::table($tableName, function (Blueprint $table): void {
                $table->bigInteger('held_minor')->default(0);
            });
        }
    }
};
