<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.affiliates', 'affiliates');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('deactivated_at')->nullable()->after('activated_at');
            $table->timestampTz('paused_at')->nullable()->after('deactivated_at');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.affiliates', 'affiliates');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn(['deactivated_at', 'paused_at']);
        });
    }
};
