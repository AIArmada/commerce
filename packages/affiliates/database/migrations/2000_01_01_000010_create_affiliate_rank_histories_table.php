<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.rank_histories', 'affiliate_rank_histories');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id');
            $table->foreignUuid('from_rank_id')->nullable();
            $table->foreignUuid('to_rank_id')->nullable();
            $table->string('reason');
            $table->timestampTz('qualified_at');
            $table->timestampsTz();

            $table->index(['affiliate_id', 'qualified_at']);
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.rank_histories', 'affiliate_rank_histories');
        Schema::dropIfExists($tableName);
    }
};
