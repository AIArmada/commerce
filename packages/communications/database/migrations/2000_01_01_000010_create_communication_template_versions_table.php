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

        Schema::create(config('communications.database.tables.template_versions', 'communication_template_versions'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->foreignUuid('template_id');
            $table->integer('version');
            $table->string('channel');
            $table->string('locale')->nullable();
            $table->text('subject')->nullable();
            $table->longText('content_text')->nullable();
            $table->longText('content_html')->nullable();
            $table->{$jsonType}('payload')->nullable();
            $table->{$jsonType}('variables_schema')->nullable();
            $table->string('checksum');
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['template_id', 'version']);
            $table->index(['template_id', 'channel', 'locale']);
        });
    }
};
