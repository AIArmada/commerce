<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.attributions', 'affiliate_attributions');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->index();
            $table->string('affiliate_code', 64)->index();
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_key')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('subject_instance')->nullable();
            $table->string('subject_title_snapshot', 200)->nullable();
            $table->string('cart_identifier')->nullable();
            $table->string('cart_instance')->default('default');
            $table->uuid('affiliate_link_id')->nullable()->index();
            $table->uuid('affiliate_program_id')->nullable()->index();
            $table->string('attribution_type', 32)->nullable()->index();
            $table->string('visitor_key', 160)->nullable()->index();
            $table->string('channel', 64)->nullable()->index();
            $table->string('origin', 32)->nullable()->index();
            $table->uuid('sharer_user_id')->nullable()->index();
            $table->string('cookie_value', 120)->nullable()->unique();
            $table->string('voucher_code', 64)->nullable()->index();
            $table->{$jsonType}('commission_override')->nullable();
            $table->{$jsonType}('upline_levels')->nullable();
            $table->string('source', 64)->nullable();
            $table->string('medium', 64)->nullable();
            $table->string('campaign', 64)->nullable();
            $table->string('term', 64)->nullable();
            $table->string('content', 64)->nullable();
            $table->string('landing_url')->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->uuid('user_id')->nullable()->index();
            $table->string('fingerprint', 128)->nullable()->index();
            $table->nullableUuidMorphs('owner');
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('first_seen_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('last_cookie_seen_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['cart_identifier', 'cart_instance'], 'affiliate_attributions_cart_index');
            $table->index('cookie_value', 'affiliate_attributions_cookie_index');

            $table->index(['affiliate_id', 'first_seen_at'], 'affiliate_attributions_timeline_idx');
            $table->index(['subject_key', 'subject_instance'], 'affiliate_attributions_subject_idx');
            $table->index(['subject_type', 'subject_key'], 'affiliate_attributions_subject_type_idx');
            $table->index(['subject_type', 'subject_id'], 'affiliate_attributions_subject_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.attributions', 'affiliate_attributions'));
    }
};
