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

        Schema::create(config('communications.database.tables.recipients', 'communication_recipients'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->foreignUuid('communication_id')->index();
            $table->string('recipient_type')->nullable();
            $table->string('recipient_id')->nullable();
            $table->string('role');
            $table->string('external_key')->nullable()->index();
            $table->string('display_name')->nullable();
            $table->string('locale')->nullable();
            $table->string('timezone')->nullable();
            $table->{$jsonType}('snapshot')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['communication_id', 'role']);
            $table->index(['recipient_type', 'recipient_id']);
        });
    }
};
