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

        Schema::create(config('seating.database.tables.seat_maps'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('seatable');
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->integer('version')->default(1);
            $table->string('status')->default('active')->index();
            $table->{$jsonType}('layout_metadata')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seating.database.tables.seat_maps'));
    }
};
