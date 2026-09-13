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

        Schema::create(config('events.database.tables.event_audiences', 'event_audiences'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('audience_type')->index();
            $table->string('value')->index();
            $table->integer('sort_order')->default(0)->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
