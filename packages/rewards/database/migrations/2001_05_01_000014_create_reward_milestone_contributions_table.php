<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('rewards', 'jsonb');

        Schema::create(config('rewards.database.tables.milestone_contributions', 'reward_milestone_contributions'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->foreignUuid('cycle_id')->index();
            $table->string('source_business_key', 191)->index();
            $table->string('contributor_type', 64);
            $table->string('contributor_id', 64);
            $table->string('contributor_role', 32);
            $table->string('outcome', 64);
            $table->{$jsonType}('source_snapshot');
            $table->timestampTz('occurred_at');
            $table->timestampTz('contributed_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revoke_reason', 191)->nullable();
            $table->timestampsTz();

            $table->unique(['cycle_id', 'source_business_key'], 'reward_contributions_cycle_source_unique');
            $table->index(['cycle_id', 'revoked_at'], 'reward_contributions_cycle_revoked_idx');
        });
    }
};
