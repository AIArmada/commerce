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

        Schema::create(config('communications.database.tables.attachments', 'communication_attachments'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->foreignUuid('communication_id')->index();
            $table->foreignUuid('content_id')->nullable();
            $table->string('attachable_type')->nullable();
            $table->string('attachable_id')->nullable();
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->integer('size_bytes')->nullable();
            $table->string('checksum')->nullable();
            $table->string('inline_content_id')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['attachable_type', 'attachable_id']);
        });
    }
};
