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

        Schema::create(config('events.database.tables.event_update_items', 'event_update_items'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_update_id')->index();
            $table->string('field_key')->index();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->{$jsonType}('old_value_json')->nullable();
            $table->{$jsonType}('new_value_json')->nullable();
            $table->integer('sort_order')->default(0);
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
