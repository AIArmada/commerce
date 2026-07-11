<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.conversions', 'affiliate_conversions');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->index();
            $table->foreignUuid('affiliate_attribution_id')->nullable()->index();
            $table->foreignUuid('affiliate_payout_id')->nullable()->index();
            $table->string('affiliate_code', 64)->index();
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_identifier')->nullable();
            $table->string('subject_instance')->nullable();
            $table->string('subject_title_snapshot', 200)->nullable();
            $table->string('cart_identifier')->nullable();
            $table->string('cart_instance')->nullable();
            $table->string('voucher_code', 64)->nullable();
            $table->string('order_reference', 120)->nullable();
            $table->string('external_reference', 120)->nullable();
            $table->string('conversion_type', 64)->nullable();
            $table->string('performance_bonus_key', 160)->nullable()->unique();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('commission_minor')->default(0);
            $table->unsignedBigInteger('value_minor')->default(0);
            $table->string('commission_currency', 3)->default(config('affiliates.currency.default', 'USD'))->index();
            $table->string('status', 32)->default(config('affiliates.commissions.default_status', 'pending'))->index();
            $table->string('channel')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('occurred_at')->nullable()->index();
            $table->timestampTz('approved_at')->nullable()->index();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();

            $table->index(['affiliate_id', 'status'], 'affiliate_conversions_affiliate_status_idx');
            $table->index(['status', 'occurred_at'], 'affiliate_conversions_status_date_idx');
            $table->index(['subject_identifier', 'subject_instance'], 'affiliate_conversions_subject_idx');
            $table->index('external_reference', 'affiliate_conversions_external_ref_idx');
            $table->index('conversion_type', 'affiliate_conversions_type_idx');
            $table->index(['subject_type', 'subject_identifier'], 'affiliate_conversions_subject_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.conversions', 'affiliate_conversions'));
    }
};
