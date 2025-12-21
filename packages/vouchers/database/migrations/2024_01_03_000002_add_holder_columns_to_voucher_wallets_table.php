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
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['voucher_wallets'] ?? $prefix . 'voucher_wallets';

        if (Schema::hasColumn($tableName, 'holder_type')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('holder_type')->nullable()->index();
            $table->uuid('holder_id')->nullable()->index();
            $table->index(['holder_type', 'holder_id'], 'voucher_wallets_holder_idx');
        });

        DB::table($tableName)
            ->whereNotNull('owner_type')
            ->update([
                'holder_type' => DB::raw('owner_type'),
                'holder_id' => DB::raw('owner_id'),
            ]);

        DB::table($tableName)->update([
            'owner_type' => null,
            'owner_id' => null,
        ]);

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropUnique(['voucher_id', 'owner_type', 'owner_id', 'is_redeemed']);
            $table->unique(['voucher_id', 'holder_type', 'holder_id', 'is_redeemed'], 'voucher_wallets_holder_unique');
        });
    }

    public function down(): void
    {
        //
    }
};
