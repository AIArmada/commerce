<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = (string) commerce_json_column_type('referrals', (string) config('referrals.database.json_column_type', 'jsonb'));

        Schema::create(config('referrals.database.tables.rewards', 'referral_rewards'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invite_id')->index();
            $table->foreignUuid('program_id')->index();
            $table->string('side', 16);
            $table->nullableUuidMorphs('beneficiary');
            $table->string('driver', 32);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->unsignedBigInteger('points')->nullable();
            $table->char('currency', 3)->nullable();
            $table->{$jsonType}('spec');
            $table->string('status', 16)->default('pending')->index();
            $table->string('idempotency_key', 64)->unique();
            $table->string('external_reference')->nullable();
            $table->timestampTz('fulfilled_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['invite_id', 'side'], 'referral_rewards_invite_side_unique');
            $table->index(['program_id', 'status'], 'referral_rewards_program_status_idx');
        });
    }
};
