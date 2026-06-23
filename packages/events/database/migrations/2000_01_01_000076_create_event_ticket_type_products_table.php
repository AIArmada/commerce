<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('events.database.tables.event_ticket_type_products', 'event_ticket_type_products');
        $jsonType = config('events.database.json_column_type', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();

            $table->string('event_ticket_type_id', 36)->nullable()->index();

            $table->string('product_type', 255)->nullable();
            $table->string('product_id', 36)->nullable()->index();

            $table->string('variant_type', 255)->nullable();
            $table->string('variant_id', 36)->nullable()->index();

            $table->integer('quantity')->default(1);
            $table->string('inclusion_mode', 20)->default('required')->index();
            $table->integer('sort_order')->default(0);

            $table->{$jsonType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['event_ticket_type_id', 'product_id', 'variant_id'], 'ticket_type_product_variant_idx');
        });
    }
};
