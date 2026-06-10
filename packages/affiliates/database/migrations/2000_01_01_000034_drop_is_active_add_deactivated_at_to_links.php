<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.links', 'affiliate_links');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('deactivated_at')->nullable();
        });

        DB::table($tableName)->where('is_active', false)->update([
            'deactivated_at' => now(),
        ]);

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.links', 'affiliate_links');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });

        DB::table($tableName)->whereNotNull('deactivated_at')->update([
            'is_active' => false,
        ]);

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('deactivated_at');
        });
    }
};
