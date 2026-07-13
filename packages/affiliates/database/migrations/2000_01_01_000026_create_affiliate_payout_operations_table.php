<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('affiliates.database.tables.payout_operations', 'affiliate_payout_operations'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id');
            $table->uuid('affiliate_payout_id')->nullable()->unique();
            $table->string('operation_key', 160)->unique();
            $table->string('status', 32)->default('claimed')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->unsignedBigInteger('payout_sequence')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->string('last_error_code', 64)->nullable();
            $table->timestampTz('claimed_at');
            $table->timestampTz('lease_expires_at')->nullable()->index();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('funds_released_at')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestampsTz();

            $table->index(['affiliate_id', 'status']);
            $table->unique(['affiliate_id', 'payout_sequence'], 'affiliate_payout_operation_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.payout_operations', 'affiliate_payout_operations'));
    }
};
