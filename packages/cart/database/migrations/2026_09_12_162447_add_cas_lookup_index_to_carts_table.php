<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('cart.database.table', 'carts');
        $indexName = "{$tableName}_identifier_instance_version_index";

        if (! Schema::hasTable($tableName)
            || Schema::hasIndex($tableName, $indexName)
            || Schema::hasIndex($tableName, ['identifier', 'instance', 'version'])) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->index(['identifier', 'instance', 'version'], $indexName);
        });
    }

    public function down(): void
    {
        $tableName = (string) config('cart.database.table', 'carts');
        $indexName = "{$tableName}_identifier_instance_version_index";

        if (! Schema::hasTable($tableName) || ! Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }
};
