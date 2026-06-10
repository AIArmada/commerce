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
        $tableName = config('products.database.tables.options', 'product_options');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('visibility')->default('visible')->after('is_visible');
            $table->timestampTz('hidden_at')->nullable()->after('visibility');
        });

        DB::statement("UPDATE {$tableName} SET visibility = CASE WHEN is_visible THEN 'visible' ELSE 'hidden' END");
        DB::statement("UPDATE {$tableName} SET hidden_at = updated_at WHERE is_visible = false");

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('is_visible');

            $table->index('visibility');
        });
    }

    public function down(): void
    {
        // No down() required per monorepo guidelines
    }
};
