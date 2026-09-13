<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('cashier-chip.database.tables.renewal_attempts', 'cashier_chip_renewal_attempts');

        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->uuid('subscription_id');
            $table->string('status')->default('claimed')->index();
            $table->integer('amount_minor');
            $table->string('period_key')->nullable();
            $table->string('purchase_id')->nullable();
            $table->string('last_error_code')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index('subscription_id');
            $table->index(['subscription_id', 'status'], $tableName . '_subscription_status');
            $table->unique(['subscription_id', 'period_key'], str_replace(['.', '-', ' '], '_', $tableName) . '_subscription_period_unique');
        });
    }
};
