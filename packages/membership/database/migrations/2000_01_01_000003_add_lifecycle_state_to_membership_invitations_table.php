<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('membership.database.tables.invitations', 'membership_invitations');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'status')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('status')->default('pending')->index();
            });
        }

        if (! Schema::hasColumn($tableName, 'last_state_change_at')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestampTz('last_state_change_at')->nullable()->index();
            });
        }

        if (! Schema::hasColumn($tableName, 'expired_at')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestampTz('expired_at')->nullable()->index();
            });
        }

        DB::table($tableName)
            ->whereNotNull('revoked_at')
            ->whereNull('last_state_change_at')
            ->update([
                'status' => 'revoked',
                'last_state_change_at' => DB::raw('COALESCE(revoked_at, created_at)'),
            ]);

        DB::table($tableName)
            ->whereNull('revoked_at')
            ->whereNotNull('accepted_at')
            ->whereNull('last_state_change_at')
            ->update([
                'status' => 'accepted',
                'last_state_change_at' => DB::raw('COALESCE(accepted_at, created_at)'),
            ]);

        DB::table($tableName)
            ->whereNull('revoked_at')
            ->whereNull('accepted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', CarbonImmutable::now())
            ->whereNull('last_state_change_at')
            ->update([
                'status' => 'expired',
                'expired_at' => DB::raw('COALESCE(expires_at, created_at)'),
                'last_state_change_at' => DB::raw('COALESCE(expires_at, created_at)'),
            ]);

        DB::table($tableName)
            ->where('status', 'expired')
            ->whereNull('expired_at')
            ->update([
                'expired_at' => DB::raw('COALESCE(expires_at, created_at)'),
            ]);

        DB::table($tableName)
            ->whereNull('last_state_change_at')
            ->update([
                'last_state_change_at' => DB::raw('created_at'),
            ]);
    }
};
