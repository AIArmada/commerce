<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.payout_methods', 'affiliate_payout_methods');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('is_verified');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.payout_methods', 'affiliate_payout_methods');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->boolean('is_verified')->default(false);
        });
    }
};
