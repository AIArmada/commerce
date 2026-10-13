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

        Schema::create(config('rewards.database.tables.budget_movements', 'reward_budget_movements'), function (Blueprint $table): void {

            $table->uuid('id')->primary();
            $table->string('movement_key', 191)->unique();
            $table->foreignUuid('program_id')->index();
            $table->char('currency', 3)->index();
            $table->string('kind', 16)->index();
            $table->foreignUuid('award_id')->nullable()->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('actor_type', 32);
            $table->string('actor_id', 191)->nullable();
            $table->string('actor_role', 16);
            $table->timestampTz('decided_at');
            $table->timestampsTz();
            $table->index(['program_id', 'currency', 'kind']);
        });
    }
};
