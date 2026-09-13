<?php

declare(strict_types=1);

use AIArmada\Products\Support\ProductIdentityIndexes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('products.database.tables.attribute_sets', 'product_attribute_sets');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->timestampsTz();

            $table->index(['is_default', 'position']);
        });

        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        ProductIdentityIndexes::owner($tableName, 'code');
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.attribute_sets', 'product_attribute_sets'));
    }
};
