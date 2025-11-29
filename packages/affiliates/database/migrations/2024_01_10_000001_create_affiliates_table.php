<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.table_names.affiliates', 'affiliates');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('commission_type', 24)->default('percentage');
            $table->unsignedInteger('commission_rate')->default(0); // cents or basis points
            $table->string('currency', 3)->default(config('affiliates.currency.default', 'USD'));
            $table->uuid('parent_affiliate_id')->nullable()->index();
            $table->string('default_voucher_code', 64)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('website_url')->nullable();
            $table->string('payout_terms')->nullable();
            $table->string('tracking_domain')->nullable();
            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id'], 'affiliates_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.table_names.affiliates', 'affiliates'));
    }
};
