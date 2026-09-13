<?php

/**
 * Creates the engagement_bookmark_collection_items table.
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
        $jsonType = commerce_json_column_type('engagement', 'jsonb');
        Schema::create(config('engagement.database.tables.bookmark_collection_items', 'bookmark_collection_items'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('bookmark_collection_id')->index();
            $table->uuid('bookmark_id')->index();
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestampTz('added_at')->nullable()->index();
            $table->timestampTz('removed_at')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestampsTz();
        });
    }
};
