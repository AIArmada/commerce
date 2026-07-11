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
        $tableName = config('orders.database.tables.order_payments', 'order_payments');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $duplicateGroups = DB::table($tableName)
            ->select(['order_id', 'gateway', 'transaction_id'])
            ->groupBy(['order_id', 'gateway', 'transaction_id'])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $duplicateGroup) {
            $duplicateIds = DB::table($tableName)
                ->where('order_id', $duplicateGroup->order_id)
                ->where('gateway', $duplicateGroup->gateway)
                ->where('transaction_id', $duplicateGroup->transaction_id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            DB::table($tableName)
                ->whereIn('id', $duplicateIds->slice(1)->values()->all())
                ->delete();
        }

        if (! Schema::hasIndex($tableName, 'order_payments_order_gateway_transaction_unique')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique(
                    ['order_id', 'gateway', 'transaction_id'],
                    'order_payments_order_gateway_transaction_unique',
                );
            });
        }
    }
};
