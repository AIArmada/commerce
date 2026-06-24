<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('communications.database.json_column_type', 'jsonb');

        Schema::create(config('communications.database.tables.notification_inboxes', 'notification_inboxes'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->nullableUuidMorphs('recipient');
            $table->foreignUuid('communication_id')->nullable();
            $table->string('family');
            $table->string('priority');
            $table->string('trigger');
            $table->string('title');
            $table->text('body')->nullable();
            $table->{$jsonType}('data')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampsTz();

            $table->index(['recipient_type', 'recipient_id', 'read_at']);
            $table->index(['family']);
            $table->index(['priority']);
            $table->index(['communication_id']);
        });
    }
};
