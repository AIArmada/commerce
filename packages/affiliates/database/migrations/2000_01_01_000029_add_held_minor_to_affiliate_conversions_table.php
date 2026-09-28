<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.conversions', 'conversions');

        if (! Schema::hasColumn($tableName, 'held_minor')) {
            // Mirrors commission_minor's type exactly: held copies it.
            Schema::table($tableName, function (Blueprint $table): void {
                $table->bigInteger('held_minor')->default(0);
            });
        }

        // Initialize the new column for rows that predate it. A hold is
        // only real when the balance pool actually carries it, so the
        // backfill is gated on a covering balance row: off-record rows
        // (sync disabled, missing affiliate, drained pool) keep held 0
        // and are credited in full on approval instead. This is column
        // initialization at deploy, not historical repair.
        $balancesTable = config('affiliates.database.tables.balances', 'affiliate_balances');

        if (! Schema::hasTable($balancesTable)) {
            return;
        }

        $grammar = DB::getQueryGrammar();
        $balanceHolding = $grammar->wrapTable($balancesTable) . '.' . $grammar->wrap('holding_minor');
        $conversionCommission = $grammar->wrapTable($tableName) . '.' . $grammar->wrap('commission_minor');
        $balanceCurrency = $grammar->wrapTable($balancesTable) . '.' . $grammar->wrap('currency');
        $conversionCurrency = $grammar->wrapTable($tableName) . '.' . $grammar->wrap('commission_currency');

        DB::table($tableName)
            ->whereIn('status', ['pending', 'qualified'])
            ->where('held_minor', 0)
            ->whereExists(function ($query) use ($tableName, $balancesTable, $balanceHolding, $conversionCommission, $balanceCurrency, $conversionCurrency): void {
                $query->select(DB::raw('1'))
                    ->from($balancesTable)
                    ->whereColumn($balancesTable . '.affiliate_id', $tableName . '.affiliate_id')
                    ->whereRaw("UPPER({$balanceCurrency}) = UPPER({$conversionCurrency})")
                    ->whereRaw("{$balanceHolding} >= {$conversionCommission}");
            })
            ->update(['held_minor' => new Expression('commission_minor')]);
    }
};
