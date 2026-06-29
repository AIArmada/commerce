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

        Schema::create(config('communications.database.tables.contents', 'communication_contents'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->foreignUuid('communication_id')->index();
            $table->foreignUuid('recipient_id')->nullable()->index();
            $table->string('channel');
            $table->string('locale')->nullable();
            $table->foreignUuid('template_id')->nullable();
            $table->foreignUuid('template_version_id')->nullable();
            $table->text('subject')->nullable();
            $table->longText('content_text')->nullable();
            $table->longText('content_html')->nullable();
            $table->{$jsonType}('payload')->nullable();
            $table->string('checksum')->nullable()->index();
            $table->timestampTz('rendered_at');
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['communication_id', 'channel', 'locale']);
        });
    }
};
