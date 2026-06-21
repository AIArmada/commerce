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

        Schema::create(config('communications.database.tables.communication_preferences', 'communication_preferences'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('recipient_type');
            $table->string('recipient_id');
            $table->string('channel')->nullable();
            $table->string('category')->nullable();
            $table->string('locale')->nullable();
            $table->string('timezone')->nullable();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('quiet_hours_timezone')->nullable();
            $table->timestampTz('enabled_at')->nullable()->index();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampTz('opted_in_at')->nullable();
            $table->timestampTz('opted_out_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->string('source')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['owner_type', 'owner_id', 'channel', 'category']);
        });
    }
};
