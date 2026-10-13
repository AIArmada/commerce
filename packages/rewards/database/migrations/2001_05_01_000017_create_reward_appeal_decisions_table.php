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

        Schema::create(config('rewards.database.tables.appeal_decisions', 'reward_appeal_decisions'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->foreignUuid('appeal_id')->index();
            $table->unsignedInteger('seq');
            $table->string('from_status', 16);
            $table->string('to_status', 16);
            $table->string('actor_type', 64);
            $table->string('actor_id', 64)->nullable();
            $table->string('actor_role', 32);
            $table->string('reason', 191);
            $table->{$jsonType}('evidence')->nullable();
            $table->timestampTz('decided_at');
            $table->timestampsTz();

            $table->unique(['appeal_id', 'seq'], 'reward_appeal_decisions_appeal_seq_unique');
        });
    }
};
