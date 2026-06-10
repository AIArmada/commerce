<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.payouts', 'affiliate_payouts');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('failed_at')->nullable()->after('paid_at');
            $table->timestampTz('cancelled_at')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.payouts', 'affiliate_payouts');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn(['failed_at', 'cancelled_at']);
        });
    }
};
