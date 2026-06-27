<?php

/**
 * Creates the engagement_shares table.
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
        Schema::create(config('engagement.database.tables.shares', 'engagement_shares'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('sharer_type')->nullable()->index();
            $table->uuid('sharer_id')->nullable()->index();
            $table->string('shareable_type')->index();
            $table->uuid('shareable_id')->index();
            $table->string('channel')->nullable()->index();
            $table->string('destination')->nullable();
            $table->text('share_url')->nullable();
            $table->string('share_token')->nullable()->index();
            $table->text('message')->nullable();
            $table->string('status')->index();
            $table->timestampTz('share_intent_at')->nullable();
            $table->timestampTz('shared_at')->nullable()->index();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('expired_at')->nullable()->index();
            $table->timestampTz('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableMorphs('owner');
            $table->timestampsTz();
            $table->index(['sharer_type', 'sharer_id']);
            $table->index(['shareable_type', 'shareable_id']);
        });
    }
};
