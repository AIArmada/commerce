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

        Schema::create(config('referrals.database.tables.invites', 'referral_invites'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->index();
            $table->nullableUuidMorphs('referrer');
            $table->string('invitee_hash', 64);
            $table->text('invitee_contact_encrypted')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 16)->default('pending')->index();
            $table->nullableUuidMorphs('accepted_by');
            $table->string('qualification_business_key', 191)->nullable()->unique();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('qualified_at')->nullable();
            $table->timestampTz('rewarded_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['program_id', 'invitee_hash', 'status'], 'referral_invites_claim_lookup_idx');
            $table->index(['program_id', 'referrer_type', 'referrer_id', 'status'], 'referral_invites_referrer_cap_idx');
            $table->index(['program_id', 'status', 'expired_at'], 'referral_invites_expiry_idx');
        });
    }
};
