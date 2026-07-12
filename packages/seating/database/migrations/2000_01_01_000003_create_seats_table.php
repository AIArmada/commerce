<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('seating', 'json');

        Schema::create(config('seating.database.tables.seats'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->uuid('seat_section_id')->index();
            $table->string('row_label');
            $table->string('seat_label');
            $table->integer('row_number');
            $table->integer('column_number');
            $table->string('category')->nullable()->index();
            $table->bigInteger('price_modifier')->nullable();
            $table->string('status')->default('available')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['seat_section_id', 'row_number', 'column_number']);
            $table->index(['seat_section_id', 'category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seating.database.tables.seats'));
    }
};
