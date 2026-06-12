<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('events.database.json_column_type', 'jsonb');

        Schema::create(config('events.database.tables.event_series', 'event_series'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->string('title');
            $table->string('slug')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('series_type')->index();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->boolean('is_dynamic')->default(false);
            $table->{$jsonType}('dynamic_rule_json')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
