<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.payout_holds', 'affiliate_payout_holds');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUuid('placed_by')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();

            $table->index(['affiliate_id', 'released_at']);
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.payout_holds', 'affiliate_payout_holds');
        Schema::dropIfExists($tableName);
    }
};
