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

        Schema::create(config('rewards.database.tables.recoveries', 'reward_recoveries'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->string('recovery_key', 191)->unique();
            $table->foreignUuid('award_id')->index();
            $table->foreignUuid('payout_request_id')->nullable()->index();
            $table->foreignUuid('grant_id')->nullable()->index();
            $table->string('kind', 16)->index();
            $table->string('status', 16)->index();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('points')->nullable();
            $table->string('unit', 16)->nullable();
            $table->{$jsonType}('evidence')->nullable();
            $table->string('actor_type', 32);
            $table->string('actor_id', 191)->nullable();
            $table->string('actor_role', 16);
            $table->timestampTz('opened_at');
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('written_off_at')->nullable();
            $table->timestampsTz();
        });
    }
};
