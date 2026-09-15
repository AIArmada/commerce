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
        $tableName = (string) config('products.database.tables.attribute_values', 'product_attribute_values');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            // The attribute this value belongs to
            $table->foreignUuid('attribute_id');

            // Polymorphic relation to Product or Variant
            $table->uuidMorphs('attributable');

            // The actual value (stored as text, cast by attribute type)
            $table->text('value')->nullable();

            // For translatable attributes
            $table->string('locale', 10)->nullable();

            $table->timestampsTz();

            $table->index('attribute_id');
        });

        ProductIdentityIndexes::attributeValues($tableName);
    }
};
