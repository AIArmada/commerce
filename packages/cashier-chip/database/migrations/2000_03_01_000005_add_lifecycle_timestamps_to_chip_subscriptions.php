<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = config('cashier-chip.database.table_prefix', 'cashier_chip_');
        $subscriptionsTable = config('cashier-chip.database.tables.subscriptions', $tablePrefix . 'subscriptions');

        Schema::table($subscriptionsTable, function (Blueprint $table): void {
            $table->timestampTz('canceled_at')->nullable()->after('ends_at');
            $table->timestampTz('paused_at')->nullable()->after('canceled_at');
            $table->timestampTz('past_due_at')->nullable()->after('paused_at');
            $table->timestampTz('trial_started_at')->nullable()->after('past_due_at');
            $table->timestampTz('renewed_at')->nullable()->after('trial_started_at');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('cashier-chip.database.table_prefix', 'cashier_chip_');
        $subscriptionsTable = config('cashier-chip.database.tables.subscriptions', $tablePrefix . 'subscriptions');

        Schema::table($subscriptionsTable, function (Blueprint $table): void {
            $table->dropColumn(['canceled_at', 'paused_at', 'past_due_at', 'trial_started_at', 'renewed_at']);
        });
    }
};
