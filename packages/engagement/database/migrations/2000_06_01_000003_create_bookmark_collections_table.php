<?php

/**
 * Creates the engagement_bookmark_collections table.
 * Part of the aiarmada/engagement package.
 *
 * @see https://github.com/aiarmada/engagement/docs
 */
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('engagement.database.json_column_type', 'jsonb');
        Schema::create(config('engagement.database.tables.bookmark_collections', 'bookmark_collections'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('slug')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('visibility')->index();
            $table->string('status')->index();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestampTz('archived_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
