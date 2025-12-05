<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vouchers.table_names.gift_card_transactions', 'gift_card_transactions');
        $giftCardsTable = config('vouchers.table_names.gift_cards', 'gift_cards');

        Schema::create($tableName, function (Blueprint $table) use ($giftCardsTable): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('gift_card_id');

            // Transaction Type
            $table->string('type');

            // Amount (positive for credit, negative for debit)
            $table->bigInteger('amount');
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');

            // Reference (Order, refund, etc.)
            $table->nullableUuidMorphs('reference');
            $table->string('description')->nullable();

            // Actor (User who initiated)
            $table->nullableUuidMorphs('actor');

            // Metadata
            $jsonType = (string) commerce_json_column_type('vouchers', 'json');
            $table->{$jsonType}('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['gift_card_id', 'created_at'], 'gift_card_tx_card_date_idx');
            $table->index(['reference_type', 'reference_id'], 'gift_card_tx_reference_idx');
            $table->index('type');
        });

        // Create GIN indexes for PostgreSQL
        $jsonColumnType = commerce_json_column_type('vouchers', 'json');

        if (
            $jsonColumnType === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$tableName}_metadata_gin_index ON \"{$tableName}\" USING GIN (\"metadata\")");
        }
    }

    public function down(): void
    {
        $tableName = config('vouchers.table_names.gift_card_transactions', 'gift_card_transactions');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS {$tableName}_metadata_gin_index");
        }

        Schema::dropIfExists($tableName);
    }
};
