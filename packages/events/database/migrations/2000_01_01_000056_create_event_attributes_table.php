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

        Schema::create(config('events.database.tables.event_attributes', 'event_attributes'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('attribute_key')->index();
            $table->text('attribute_value')->nullable();
            $table->{$jsonType}('attribute_value_json')->nullable();
            $table->string('visibility')->default('public');
            $table->integer('sort_order')->default(0);
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
