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

        Schema::create(config('events.database.tables.event_series_rules', 'event_series_rules'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_series_id')->index();
            $table->string('rule_type')->index();
            $table->string('operator')->index();
            $table->string('value')->nullable();
            $table->{$jsonType}('value_json')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
