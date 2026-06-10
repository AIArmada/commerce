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
        $tableName = config('products.database.tables.categories', 'product_categories');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('status')->default('active')->after('is_featured');
            $table->string('visibility')->default('catalog')->after('status');
            $table->timestampTz('hidden_at')->nullable()->after('visibility');
            $table->timestampTz('archived_at')->nullable()->after('hidden_at');
        });

        DB::statement("UPDATE {$tableName} SET status = CASE WHEN is_visible THEN 'active' ELSE 'hidden' END");
        DB::statement("UPDATE {$tableName} SET hidden_at = updated_at WHERE is_visible = false");

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('is_visible');

            $table->index('status');
            $table->index('hidden_at');
        });
    }

    public function down(): void
    {
        // No down() required per monorepo guidelines
    }
};
