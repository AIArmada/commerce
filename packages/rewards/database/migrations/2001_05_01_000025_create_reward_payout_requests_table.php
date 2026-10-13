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

        Schema::create(config('rewards.database.tables.payout_requests', 'reward_payout_requests'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->foreignUuid('award_id')->unique();
            $table->foreignUuid('payee_id')->index();
            $table->foreignUuid('program_id')->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor');
            $table->string('fee_payer', 16);
            $table->string('processor', 64);
            $table->string('operation_key', 191)->unique();
            $table->string('first_reference', 64)->unique();
            $table->string('external_payout_id', 191)->nullable()->index();
            $table->string('external_reference_id', 191)->nullable();
            $table->string('external_provider', 64)->nullable();
            $table->string('status', 16)->index();
            $table->string('provider_reference', 191)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->{$jsonType}('payee_snapshot');
            $table->{$jsonType}('evidence')->nullable();
            $table->string('requested_by_type', 32);
            $table->string('requested_by_id', 191)->nullable();
            $table->string('requested_by_role', 16);
            $table->string('approved_by', 191)->nullable();
            $table->string('confirmed_by', 191)->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();
        });
    }
};
