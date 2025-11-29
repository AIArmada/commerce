<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.table_names.conversions', 'affiliate_conversions');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->constrained(
                table: config('affiliates.table_names.affiliates', 'affiliates')
            );
            $table->foreignUuid('affiliate_attribution_id')
                ->nullable()
                ->constrained(
                    table: config('affiliates.table_names.attributions', 'affiliate_attributions')
                );
            $table->string('affiliate_code', 64)->index();
            $table->string('cart_identifier')->nullable();
            $table->string('cart_instance')->nullable();
            $table->string('voucher_code', 64)->nullable();
            $table->string('order_reference', 120)->nullable();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('commission_minor')->default(0);
            $table->string('commission_currency', 3)->default(config('affiliates.currency.default', 'USD'));
            $table->string('status', 32)->default(config('affiliates.commissions.default_status', 'pending'))->index();
            $table->string('channel')->nullable();
            $table->string('owner_type')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id'], 'affiliate_conversions_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.table_names.conversions', 'affiliate_conversions'));
    }
};
