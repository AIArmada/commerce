<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('pricing.database.tables.price_tiers', 'price_tiers');
        $indexName = 'price_tiers_tierable_lookup_idx';

        if (! Schema::hasTable($tableName) || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->index(['tierable_type', 'tierable_id', 'price_list_id'], $indexName);
        });
    }

    public function down(): void
    {
        $tableName = config('pricing.database.tables.price_tiers', 'price_tiers');
        $indexName = 'price_tiers_tierable_lookup_idx';

        if (! Schema::hasTable($tableName) || ! Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }
};
