<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.affiliates', 'affiliates');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('commission_type', 24)->default('percentage')->index();
            $table->unsignedInteger('commission_rate')->default(0); // cents or basis points
            $table->string('currency', 3)->default(config('affiliates.currency.default', 'USD'))->index();
            $table->uuid('parent_affiliate_id')->nullable()->index();
            $table->foreignUuid('rank_id')->nullable();
            $table->integer('network_depth')->default(0);
            $table->integer('direct_downline_count')->default(0);
            $table->integer('total_downline_count')->default(0);
            $table->string('default_voucher_code', 64)->nullable();
            $table->string('payout_terms')->nullable();
            $table->string('tracking_domain')->nullable();
            $table->string('external_reference_type', 64)->nullable();
            $table->string('external_reference', 160)->nullable();
            $table->nullableUuidMorphs('owner');
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampTz('paused_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'activated_at'], 'affiliates_active_idx');
            $table->unique(['external_reference_type', 'external_reference'], 'affiliates_external_ref_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.affiliates', 'affiliates'));
    }
};
