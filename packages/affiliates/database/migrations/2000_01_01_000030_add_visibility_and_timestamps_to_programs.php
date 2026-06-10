<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ProgramVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.programs', 'affiliate_programs');

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $table->string('visibility', 32)->default('private')->after('status');
            $table->timestampTz('paused_at')->nullable()->after('ends_at');
            $table->timestampTz('archived_at')->nullable()->after('paused_at');
        });

        DB::table($tableName)->update([
            'visibility' => DB::raw("CASE WHEN is_public = true THEN 'public' ELSE 'private' END"),
        ]);

        DB::statement('DROP INDEX IF EXISTS "' . $tableName . '_is_public_status_index"');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('is_public');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.programs', 'affiliate_programs');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->boolean('is_public')->default(true);
            $table->index(['is_public', 'status']);
        });

        DB::table($tableName)->update([
            'is_public' => DB::raw("CASE WHEN visibility = 'public' THEN 1 ELSE 0 END"),
        ]);

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn(['visibility', 'paused_at', 'archived_at']);
        });
    }
};
