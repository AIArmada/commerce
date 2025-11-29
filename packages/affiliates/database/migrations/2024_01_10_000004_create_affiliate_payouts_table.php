<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create(config('affiliates.table_names.payouts', 'affiliate_payouts'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedInteger('conversion_count')->default(0);
            $table->string('currency', 3)->default(config('affiliates.payouts.currency', 'USD'));
            $table->{$jsonType}('metadata')->nullable();
            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.table_names.payouts', 'affiliate_payouts'));
    }
};
