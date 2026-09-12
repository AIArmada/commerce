<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config(
            'cashier-chip.database.tables.renewal_attempts',
            'cashier_chip_renewal_attempts',
        );
        $indexName = str_replace(['.', '-', ' '], '_', $tableName)
            . '_subscription_period_unique';
        $columns = ['subscription_id', 'period_key'];

        if (! Schema::hasTable($tableName)
            || Schema::hasIndex($tableName, $indexName)
            || Schema::hasIndex($tableName, $columns, 'unique')) {
            return;
        }

        $connection = Schema::getConnection();
        $driver = ConnectionDriver::name($connection);

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            $grammar = $connection->getQueryGrammar();

            $connection->statement(sprintf(
                'CREATE UNIQUE INDEX IF NOT EXISTS %s ON %s (%s, %s) WHERE %s IS NOT NULL',
                $grammar->wrap($indexName),
                $grammar->wrapTable($tableName),
                $grammar->wrap('subscription_id'),
                $grammar->wrap('period_key'),
                $grammar->wrap('period_key'),
            ));

            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->unique($columns, $indexName);
        });
    }
};
