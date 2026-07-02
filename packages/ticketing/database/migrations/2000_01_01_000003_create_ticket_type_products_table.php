<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('ticketing.database.json_column_type', 'jsonb');

        Schema::create(config('ticketing.database.tables.ticket_type_products'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('ticket_type_id', 36)->nullable()->index();
            $table->string('product_type')->nullable();
            $table->string('product_id', 36)->nullable()->index();
            $table->string('variant_type')->nullable();
            $table->string('variant_id', 36)->nullable()->index();
            $table->integer('quantity')->default(1);
            $table->string('inclusion_mode', 20)->default('required')->index();
            $table->integer('sort_order')->default(0);
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();

            $table->index(['ticket_type_id', 'product_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ticketing.database.tables.ticket_type_products'));
    }
};
