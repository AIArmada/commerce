<?php

/**
 * Creates the engagement_interaction_counters table.
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
        Schema::create(config('engagement.database.tables.engagement_counters', 'engagement_counters'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('subject_type')->index();
            $table->uuid('subject_id')->index();
            $table->string('counter_type')->index();
            $table->string('counter_key')->default('');
            $table->bigInteger('count_value')->default(0);
            $table->timestampTz('recalculated_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestampsTz();

            $table->index(['subject_type', 'subject_id', 'counter_type'], 'engagement_counters_lookup_idx');
            $table->unique([
                'subject_type',
                'subject_id',
                'counter_type',
                'counter_key',
                'owner_type',
                'owner_id',
            ], 'engagement_counters_unique_idx');
        });
    }
};
