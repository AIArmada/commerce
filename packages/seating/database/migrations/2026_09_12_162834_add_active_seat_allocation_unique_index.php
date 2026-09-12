<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'seating_allocations_active_seat_unique';

    public function up(): void
    {
        $tableName = (string) config('seating.database.tables.seat_allocations', 'seat_allocations');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            /*
             * Drivers without partial-index predicates retain the existing
             * composite (seat_id, status) lookup index. Conversion locks the
             * seat row with FOR UPDATE as the integrity guard on those drivers.
             */
            return;
        }

        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX IF NOT EXISTS %s ON %s (%s) WHERE %s = \'active\'',
            $grammar->wrap(self::INDEX_NAME),
            $grammar->wrapTable($tableName),
            $grammar->wrap('seat_id'),
            $grammar->wrap('status'),
        ));
    }

    public function down(): void
    {
        $tableName = (string) config('seating.database.tables.seat_allocations', 'seat_allocations');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $connection = Schema::getConnection();

        if (! in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        $connection->statement(sprintf(
            'DROP INDEX IF EXISTS %s',
            $connection->getQueryGrammar()->wrap(self::INDEX_NAME),
        ));
    }
};
