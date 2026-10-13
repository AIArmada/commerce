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

        Schema::create(config('rewards.database.tables.award_entries', 'reward_award_entries'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->foreignUuid('award_id')->index();
            $table->unsignedInteger('seq');
            $table->string('from_status', 16);
            $table->string('to_status', 16);
            $table->string('actor_type', 64);
            $table->string('actor_id', 64)->nullable();
            $table->string('actor_role', 32);
            $table->string('reason', 191);
            $table->{$jsonType}('evidence')->nullable();
            $table->string('source_operation_id', 191)->nullable()->index();
            $table->timestampTz('decided_at');
            $table->timestampsTz();

            $table->unique(['award_id', 'seq'], 'reward_award_entries_award_seq_unique');
            $table->unique(['award_id', 'source_operation_id'], 'reward_award_entries_award_operation_unique');
            $table->index(['award_id', 'decided_at'], 'reward_award_entries_award_time_idx');
        });
    }
};
