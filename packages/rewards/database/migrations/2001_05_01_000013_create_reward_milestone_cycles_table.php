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

        Schema::create(config('rewards.database.tables.milestone_cycles', 'reward_milestone_cycles'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->string('recipient_type', 64)->index();
            $table->foreignUuid('recipient_id')->index();
            $table->string('family_key', 64)->index();
            $table->foreignUuid('program_id')->nullable()->index();
            $table->string('milestone_key', 64)->index();
            $table->timestampTz('window_start');
            $table->unsignedInteger('window_days');
            $table->unsignedInteger('target_count');
            $table->unsignedInteger('current_count');
            $table->{$jsonType}('reward_snapshot');
            $table->string('status', 16)->index();
            $table->foreignUuid('award_id')->nullable()->index();
            $table->timestampTz('achieved_at')->nullable();
            $table->timestampTz('invalidated_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['recipient_type', 'recipient_id', 'family_key', 'milestone_key', 'window_start'],
                'reward_cycles_recipient_family_milestone_window_unique'
            );
        });
    }
};
