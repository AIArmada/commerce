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

        Schema::create(config('rewards.database.tables.appeals', 'reward_appeals'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->foreignUuid('award_id')->index();
            $table->unsignedInteger('seq');
            $table->string('status', 16)->index();
            $table->string('opened_by_type', 64);
            $table->string('opened_by_id', 64);
            $table->string('opened_by_role', 32);
            $table->string('reason', 191);
            $table->{$jsonType}('evidence')->nullable();
            $table->timestampTz('window_ends_at');
            $table->timestampTz('opened_at');
            $table->timestampTz('in_review_at')->nullable();
            $table->timestampTz('upheld_at')->nullable();
            $table->timestampTz('overturned_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['award_id', 'seq'], 'reward_appeals_award_seq_unique');
            $table->index(['award_id', 'status'], 'reward_appeals_award_status_idx');
        });
    }
};
