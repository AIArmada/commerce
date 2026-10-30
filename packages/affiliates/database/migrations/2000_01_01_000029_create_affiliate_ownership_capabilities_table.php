<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.ownership_capabilities', 'affiliate_ownership_capabilities');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id');
            $table->nullableUuidMorphs('owner');
            $table->string('verifier_hash', 64)->unique();
            $table->string('status', 16)->default('active');
            $table->string('label')->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index('affiliate_id');
            $table->index(['affiliate_id', 'status']);
        });
    }
};
