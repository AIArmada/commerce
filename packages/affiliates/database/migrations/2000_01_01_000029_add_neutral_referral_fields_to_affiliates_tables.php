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
            $table->string('subject_identifier')->nullable();
            $table->string('subject_instance')->nullable();
            $table->index(['subject_identifier', 'subject_instance'], 'affiliate_attributions_subject_idx');
        });

        Schema::table(config('affiliates.database.tables.conversions', 'affiliate_conversions'), function (Blueprint $table): void {
            $table->string('subject_identifier')->nullable();
            $table->string('subject_instance')->nullable();
            $table->string('external_reference', 120)->nullable();
            $table->string('conversion_type', 64)->nullable();
            $table->unsignedBigInteger('value_minor')->default(0);
            $table->index(['subject_identifier', 'subject_instance'], 'affiliate_conversions_subject_idx');
            $table->index('external_reference', 'affiliate_conversions_external_ref_idx');
            $table->index('conversion_type', 'affiliate_conversions_type_idx');
        });
    }
};
