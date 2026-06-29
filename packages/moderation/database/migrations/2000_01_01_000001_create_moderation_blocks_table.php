<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('moderation.database.json_column_type', 'jsonb');

        Schema::create(config('moderation.database.tables.blocks', 'moderation_blocks'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->nullableUuidMorphs('blockable');
            $table->nullableUuidMorphs('blocked_by');
            $table->string('reason');
            $table->string('status')->default('active');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('lifted_at')->nullable();
            $table->nullableUuidMorphs('lifted_by');
            $table->text('notes')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index('status');
            $table->index('expires_at');
        });
    }
};
