<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $databaseConfig = config('cashier-chip.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cashier_chip_';
        $tables = $databaseConfig['tables'] ?? [];

        $subscriptionsTable = $tables['subscriptions'] ?? $tablePrefix . 'subscriptions';
        $itemsTable = $tables['subscription_items'] ?? $tablePrefix . 'subscription_items';

        if (! Schema::hasTable($subscriptionsTable)) {
            Schema::create($subscriptionsTable, function (Blueprint $table) use ($subscriptionsTable): void {
                $table->uuid('id')->primary();
                $table->nullableUuidMorphs('owner');
                $table->uuidMorphs('billable');
                $table->string('type');
                $table->string('chip_id')->unique();
                $table->string('chip_status');
                $table->string('chip_price')->nullable();
                $table->integer('quantity')->nullable();
                $table->string('recurring_token')->nullable();
                $table->string('billing_interval')->default('month');
                $table->integer('billing_interval_count')->default(1);
                $table->timestampTz('trial_ends_at')->nullable();
                $table->timestampTz('next_billing_at')->nullable();
                $table->timestampTz('ends_at')->nullable();
                $table->timestampTz('canceled_at')->nullable();
                $table->timestampTz('paused_at')->nullable();
                $table->timestampTz('past_due_at')->nullable();
                $table->timestampTz('trial_started_at')->nullable();
                $table->timestampTz('renewed_at')->nullable();
                $table->string('coupon_id')->nullable();
                $table->integer('coupon_discount')->nullable();
                $table->string('coupon_duration')->nullable();
                $table->timestampTz('coupon_applied_at')->nullable();
                $table->timestampsTz();

                $table->index(['billable_type', 'billable_id', 'chip_status']);
                $table->index('type');
                $table->index('recurring_token');
                $table->index('trial_ends_at');
                $table->index('next_billing_at');
                $table->index('ends_at');
                $table->index('coupon_id');
                $table->index(['billable_type', 'billable_id', 'type'], $subscriptionsTable . '_billable_type_idx');
            });
        }

        if (! Schema::hasTable($itemsTable)) {
            Schema::create($itemsTable, function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->nullableUuidMorphs('owner');
                $table->foreignUuid('subscription_id');
                $table->string('chip_id')->unique();
                $table->string('chip_product')->nullable();
                $table->string('chip_price')->nullable();
                $table->integer('quantity')->nullable();
                $table->integer('unit_amount')->nullable();
                $table->timestampsTz();

                $table->index(['subscription_id', 'chip_price']);
                $table->index('subscription_id');
                $table->index('chip_product');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $databaseConfig = config('cashier-chip.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cashier_chip_';
        $tables = $databaseConfig['tables'] ?? [];

        Schema::dropIfExists($tables['subscription_items'] ?? $tablePrefix . 'subscription_items');
        Schema::dropIfExists($tables['subscriptions'] ?? $tablePrefix . 'subscriptions');
    }
};
