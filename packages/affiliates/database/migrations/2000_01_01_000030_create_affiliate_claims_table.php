<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.claims', 'affiliate_claims');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->unique();
            $table->nullableUuidMorphs('owner');
            $table->string('claimant_type', 191);
            $table->string('claimant_id', 191);
            $table->string('capability_verifier_hash', 64)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['claimant_type', 'claimant_id']);
        });
    }
};
