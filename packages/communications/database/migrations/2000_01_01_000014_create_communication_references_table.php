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

        Schema::create(config('communications.database.tables.references', 'communication_references'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->foreignUuid('communication_id')->index();
            $table->string('reference_type');
            $table->string('reference_id');
            $table->string('role')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['reference_type', 'reference_id']);
        });
    }
};
