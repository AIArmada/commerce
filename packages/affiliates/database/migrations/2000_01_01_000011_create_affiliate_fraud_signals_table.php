<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.fraud_signals', 'affiliate_fraud_signals');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id');
            $table->foreignUuid('conversion_id')->nullable();
            $table->foreignUuid('touchpoint_id')->nullable();
            $table->string('rule_code', 50);
            $table->integer('risk_points');
            $table->string('severity', 20);
            $table->string('description');

            $jsonType = commerce_json_column_type('affiliates', 'jsonb');
            $table->addColumn($jsonType, 'evidence')->nullable();

            $table->string('status', 20)->default('detected');
            $table->timestampTz('detected_at');
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable();
            $table->timestampTz('dismissed_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();

            $table->index(['affiliate_id', 'detected_at']);
            $table->index(['rule_code', 'severity']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.fraud_signals', 'affiliate_fraud_signals');
        Schema::dropIfExists($tableName);
    }
};
