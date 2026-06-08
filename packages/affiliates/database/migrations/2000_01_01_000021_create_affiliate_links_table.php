<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.links', 'affiliate_links');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id');
            $table->foreignUuid('program_id')->nullable();
            $table->string('destination_url');
            $table->string('tracking_url');
            $table->string('short_url')->nullable();
            $table->string('custom_slug')->nullable()->unique();
            $table->string('campaign')->nullable();
            $table->string('sub_id')->nullable();
            $table->string('sub_id_2')->nullable();
            $table->string('sub_id_3')->nullable();
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_identifier')->nullable();
            $table->string('subject_instance', 64)->nullable();
            $table->string('subject_title_snapshot', 200)->nullable();
            $table->{$jsonType}('subject_metadata')->nullable();
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('affiliate_id');
            $table->index('custom_slug');
            $table->index(['affiliate_id', 'campaign']);
            $table->index(['subject_type', 'subject_identifier'], 'affiliate_links_subject_idx');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.links', 'affiliate_links');
        Schema::dropIfExists($tableName);
    }
};
