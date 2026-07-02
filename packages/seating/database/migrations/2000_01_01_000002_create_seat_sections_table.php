<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('seating.database.json_column_type', 'json');

        Schema::create(config('seating.database.tables.seat_sections'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->uuid('seat_map_id')->index();
            $table->string('name');
            $table->string('code');
            $table->integer('sort_order')->default(0);
            $table->integer('capacity');
            $table->string('color')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seating.database.tables.seat_sections'));
    }
};
