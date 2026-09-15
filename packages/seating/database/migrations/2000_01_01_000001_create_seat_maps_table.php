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

        Schema::create(config('seating.database.tables.seat_maps', 'seat_maps'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('seatable_type')->nullable();
            $table->string('seatable_id')->nullable();
            $table->index(['seatable_type', 'seatable_id']);
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->integer('version')->default(1);
            $table->string('status')->default('active')->index();
            $table->{$jsonType}('layout_metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
