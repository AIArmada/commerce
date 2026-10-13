<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('cart.database.tables.snapshot_items', 'cart_snapshot_items');
        $jsonType = (string) commerce_json_column_type('cart', config('cart.database.json_column_type', 'jsonb'));
        $usePostgresGin = $jsonType === 'jsonb' && ConnectionDriver::name(Schema::getConnection()) === 'pgsql';

        Schema::create($tableName, function (Blueprint $table) use ($jsonType, $usePostgresGin): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cart_id');
            $table->string('item_id')->index(); // The original cart item ID
            $table->string('name');
            $table->unsignedInteger('price'); // Price in cents (from Money object)
            $table->unsignedInteger('quantity');
            $table->{$jsonType}('attributes')->nullable();
            $table->{$jsonType}('conditions')->nullable();
            $table->string('associated_model')->nullable();
            $table->timestampsTz();

            // Indexes for performance
            $table->index(['cart_id', 'item_id']);
            $table->index('cart_id');
            $table->index('name');
            $table->index('price');
            $table->index('quantity');
            $table->index('associated_model');
            $table->index('created_at');
            $table->index('updated_at');

            if ($usePostgresGin) {
                $table->index('attributes', $tableName . '_attributes_gin_index', 'gin');
                $table->index('conditions', $tableName . '_conditions_gin_index', 'gin');
            }
        });
    }
};
