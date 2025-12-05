<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vouchers.table_names.vouchers', 'vouchers');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->foreignUuid('affiliate_id')->nullable()->after('campaign_variant_id');
            $table->index('affiliate_id');
        });
    }

    public function down(): void
    {
        $tableName = config('vouchers.table_names.vouchers', 'vouchers');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['affiliate_id']);
            $table->dropColumn('affiliate_id');
        });
    }
};
