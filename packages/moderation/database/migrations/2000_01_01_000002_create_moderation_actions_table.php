<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('moderation', 'jsonb');

        commerce_schema_create_if_missing(config('moderation.database.tables.moderation_actions', 'moderation_actions'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->nullableUuidMorphs('actionable');
            $table->nullableUuidMorphs('actioned_by');
            $table->string('type');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index('type');
        });
    }
};
