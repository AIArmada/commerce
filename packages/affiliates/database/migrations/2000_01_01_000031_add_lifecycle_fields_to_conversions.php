<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.conversions', 'affiliate_conversions');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('rejected_at')->nullable()->after('approved_at');
            $table->timestampTz('paid_at')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.conversions', 'affiliate_conversions');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn(['rejected_at', 'paid_at']);
        });
    }
};
