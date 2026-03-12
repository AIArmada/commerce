<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('affiliates.database.tables.attributions', 'affiliate_attributions'), function (Blueprint $table): void {
            $table->string('subject_type', 64)->nullable()->after('affiliate_code');
            $table->string('subject_title_snapshot', 200)->nullable()->after('subject_instance');

            $table->index(['subject_type', 'subject_identifier'], 'affiliate_attributions_subject_type_idx');
        });

        Schema::table(config('affiliates.database.tables.touchpoints', 'affiliate_touchpoints'), function (Blueprint $table): void {
            $table->string('subject_type', 64)->nullable()->after('affiliate_code');
            $table->string('subject_identifier')->nullable()->after('subject_type');
            $table->string('subject_instance', 64)->nullable()->after('subject_identifier');
            $table->string('subject_title_snapshot', 200)->nullable()->after('subject_instance');

            $table->index(['subject_type', 'subject_identifier'], 'affiliate_touchpoints_subject_idx');
        });

        Schema::table(config('affiliates.database.tables.conversions', 'affiliate_conversions'), function (Blueprint $table): void {
            $table->string('subject_type', 64)->nullable()->after('affiliate_code');
            $table->string('subject_title_snapshot', 200)->nullable()->after('subject_instance');

            $table->index(['subject_type', 'subject_identifier'], 'affiliate_conversions_subject_type_idx');
        });
    }
};
