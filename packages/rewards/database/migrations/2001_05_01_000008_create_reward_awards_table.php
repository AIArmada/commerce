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

        Schema::create(config('rewards.database.tables.awards', 'reward_awards'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->index();
            $table->foreignUuid('policy_version_id')->nullable()->index();
            $table->foreignUuid('enrollment_id')->nullable()->index();
            $table->foreignUuid('acceptance_id')->nullable()->index();
            $table->string('recipient_type', 64)->index();
            $table->foreignUuid('recipient_id')->index();
            $table->string('beneficiary_role', 32)->index();
            $table->string('source_business_key', 191)->index();
            $table->string('family_key', 64)->index();
            $table->string('component', 16)->index();
            $table->string('status', 16)->index();
            $table->{$jsonType}('amount_snapshot');
            $table->{$jsonType}('policy_snapshot');
            $table->{$jsonType}('consent_snapshot');
            $table->{$jsonType}('source_snapshot');
            $table->unsignedInteger('obligation_epoch')->default(0);
            $table->unsignedBigInteger('obligated_minor')->default(0);
            $table->timestampTz('held_at')->nullable();
            $table->timestampTz('pending_review_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('fulfilled_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->timestampTz('corrected_at')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['recipient_type', 'recipient_id', 'source_business_key', 'family_key', 'component'],
                'reward_awards_canonical_unique'
            );
            $table->index(['program_id', 'status'], 'reward_awards_program_status_idx');
            $table->index(['family_key', 'status'], 'reward_awards_family_status_idx');
        });
    }
};
