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

        Schema::create(config('affiliates.table_names.payout_events', 'affiliate_payout_events'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_payout_id')
                ->constrained(table: config('affiliates.table_names.payouts', 'affiliate_payouts'))
                ->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->{$jsonType}('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.table_names.payout_events', 'affiliate_payout_events'));
    }
};
