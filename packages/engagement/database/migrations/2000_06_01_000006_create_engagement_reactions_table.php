<?php

/**
 * Creates the engagement_reactions table.
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
        Schema::create(config('engagement.database.tables.reactions', 'reactions'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('reactor_type')->index();
            $table->uuid('reactor_id')->index();
            $table->index(['reactor_type', 'reactor_id']);
            $table->string('reactable_type')->index();
            $table->uuid('reactable_id')->index();
            $table->index(['reactable_type', 'reactable_id']);
            $table->string('reaction_type')->index();
            $table->string('status')->index();
            $table->timestampTz('reacted_at')->nullable()->index();
            $table->timestampTz('removed_at')->nullable()->index();
            $table->string('source')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestampsTz();
        });
    }
};
