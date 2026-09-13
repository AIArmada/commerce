<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('events', 'jsonb');

        Schema::create(config('events.database.tables.venues', 'venues'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_venue_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('venue_type')->nullable()->index();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
