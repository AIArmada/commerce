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

        Schema::create(config('events.database.tables.event_registration_answers', 'event_registration_answers'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_registration_id')->index();
            $table->uuid('event_registration_participant_id')->nullable()->index();
            $table->string('field_key')->index();
            $table->string('question');
            $table->text('answer')->nullable();
            $table->{$jsonType}('answer_json')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
