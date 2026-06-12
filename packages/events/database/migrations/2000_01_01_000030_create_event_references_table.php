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

        Schema::create(config('events.database.tables.event_references', 'event_references'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->string('referenceable_type')->nullable()->index();
            $table->uuid('referenceable_id')->nullable()->index();
            $table->string('reference_type')->index();
            $table->string('title')->nullable();
            $table->text('url')->nullable();
            $table->text('citation')->nullable();
            $table->string('visibility')->index();
            $table->integer('sort_order')->default(0)->index();
            $table->text('notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
