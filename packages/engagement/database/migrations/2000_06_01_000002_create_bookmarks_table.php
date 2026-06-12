<?php

/**
 * Creates the engagement_bookmarks table.
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
        Schema::create(config('engagement.database.tables.bookmarks', 'bookmarks'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('bookmarker_type')->index();
            $table->uuid('bookmarker_id')->index();
            $table->index(['bookmarker_type', 'bookmarker_id']);
            $table->string('bookmarkable_type')->index();
            $table->uuid('bookmarkable_id')->index();
            $table->index(['bookmarkable_type', 'bookmarkable_id']);
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->timestampTz('bookmarked_at')->nullable()->index();
            $table->timestampTz('removed_at')->nullable()->index();
            $table->timestampTz('archived_at')->nullable();
            $table->string('source')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
