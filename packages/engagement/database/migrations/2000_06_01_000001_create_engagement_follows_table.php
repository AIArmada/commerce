<?php

/**
 * Creates the engagement_follows table.
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
        Schema::create(config('engagement.database.tables.follows', 'follows'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('follower_type')->index();
            $table->uuid('follower_id')->index();
            $table->index(['follower_type', 'follower_id']);
            $table->string('followable_type')->index();
            $table->uuid('followable_id')->index();
            $table->index(['followable_type', 'followable_id']);
            $table->string('status')->index();
            $table->string('notification_level')->nullable()->index();
            $table->{$jsonType}('notification_preferences')->nullable();
            $table->timestampTz('followed_at')->nullable()->index();
            $table->timestampTz('muted_at')->nullable();
            $table->timestampTz('unfollowed_at')->nullable()->index();
            $table->timestampTz('blocked_at')->nullable();
            $table->string('source')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableMorphs('owner');
            $table->timestampsTz();
            $table->index(['follower_type', 'follower_id', 'followable_type', 'followable_id', 'status']);
        });
    }
};
