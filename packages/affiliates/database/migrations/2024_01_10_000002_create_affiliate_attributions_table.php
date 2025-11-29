<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.table_names.attributions', 'affiliate_attributions');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->constrained(
                table: config('affiliates.table_names.affiliates', 'affiliates')
            );
            $table->string('affiliate_code', 64)->index();
            $table->string('cart_identifier')->nullable();
            $table->string('cart_instance')->default('default');
            $table->string('cookie_value', 120)->nullable()->unique();
            $table->string('voucher_code', 64)->nullable()->index();
            $table->string('source', 64)->nullable();
            $table->string('medium', 64)->nullable();
            $table->string('campaign', 64)->nullable();
            $table->string('term', 64)->nullable();
            $table->string('content', 64)->nullable();
            $table->string('landing_url')->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('owner_type')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('first_seen_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('last_cookie_seen_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['cart_identifier', 'cart_instance'], 'affiliate_attributions_cart_index');
            $table->index('cookie_value', 'affiliate_attributions_cookie_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.table_names.attributions', 'affiliate_attributions'));
    }
};
