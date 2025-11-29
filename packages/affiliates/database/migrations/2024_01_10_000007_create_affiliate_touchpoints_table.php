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

        Schema::create(config('affiliates.table_names.touchpoints', 'affiliate_touchpoints'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_attribution_id')
                ->constrained(table: config('affiliates.table_names.attributions', 'affiliate_attributions'))
                ->cascadeOnDelete();
            $table->foreignUuid('affiliate_id')
                ->constrained(table: config('affiliates.table_names.affiliates', 'affiliates'))
                ->cascadeOnDelete();
            $table->string('affiliate_code', 64)->index();
            $table->string('source', 64)->nullable();
            $table->string('medium', 64)->nullable();
            $table->string('campaign', 64)->nullable();
            $table->string('term', 64)->nullable();
            $table->string('content', 64)->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('touched_at')->nullable()->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.table_names.touchpoints', 'affiliate_touchpoints'));
    }
};
