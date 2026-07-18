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

        Schema::create(config('affiliates.database.tables.touchpoints', 'affiliate_touchpoints'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_attribution_id')->index();
            $table->foreignUuid('affiliate_id')->index();
            $table->string('affiliate_code', 64)->index();
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_key')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('subject_instance', 64)->nullable();
            $table->string('subject_title_snapshot', 200)->nullable();
            $table->uuid('affiliate_link_id')->nullable()->index();
            $table->string('touchpoint_type', 32)->nullable()->index();
            $table->string('interaction_type', 32)->nullable()->index();
            $table->string('visitor_key', 160)->nullable()->index();
            $table->string('channel', 64)->nullable()->index();
            $table->string('origin', 32)->nullable()->index();
            $table->string('url')->nullable();
            $table->string('referrer_url')->nullable();

            $table->nullableUuidMorphs('owner');

            $table->string('source', 64)->nullable()->index();
            $table->string('medium', 64)->nullable()->index();
            $table->string('campaign', 64)->nullable()->index();
            $table->string('term', 64)->nullable();
            $table->string('content', 64)->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->string('fingerprint', 128)->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('touched_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['subject_type', 'subject_key'], 'affiliate_touchpoints_subject_key_idx');
            $table->index(['subject_type', 'subject_id'], 'affiliate_touchpoints_subject_id_idx');
            $table->index(['affiliate_attribution_id', 'touchpoint_type', 'touched_at'], 'affiliate_touchpoints_attribution_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.touchpoints', 'affiliate_touchpoints'));
    }
};
