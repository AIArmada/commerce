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

        Schema::create(config('events.database.tables.event_taxonomies', 'event_taxonomies'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_hierarchical')->default(true);
            $table->boolean('is_active')->default(true);
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
