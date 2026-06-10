<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.fraud_signals', 'affiliate_fraud_signals');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('dismissed_at')->nullable()->after('reviewed_by');
            $table->timestampTz('confirmed_at')->nullable()->after('dismissed_at');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.fraud_signals', 'affiliate_fraud_signals');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn(['dismissed_at', 'confirmed_at']);
        });
    }
};
