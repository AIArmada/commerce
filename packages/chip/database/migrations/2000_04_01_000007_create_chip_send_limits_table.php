<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        commerce_schema_create_if_missing($tablePrefix . 'send_limits', function (Blueprint $table): void {
            // Core API fields - Send Limit structure from CHIP Send API
            $table->integer('id')->primary();

            // CHIP Send expresses these amounts in major currency units (for example, 1 = RM1).
            $table->decimal('amount', 20, 2);
            $table->decimal('fee', 20, 2);
            $table->decimal('net_amount', 20, 2);

            // Classification fields straight from the API contract
            $table->string('currency', 3);
            $table->string('fee_type', 16);
            $table->string('transaction_type', 16);
            $table->string('status', 24);

            // Approval metadata
            $table->integer('approvals_required')->default(0);
            $table->integer('approvals_received')->default(0);

            // Settlement and lifecycle timestamps
            $table->date('from_settlement')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            // Owner scoping
            $table->nullableMorphs('owner');

            // Indexes for efficient querying by status and currency buckets
            $table->index(['status', 'transaction_type']);
            $table->index(['currency', 'from_settlement']);
        });
    }
};
